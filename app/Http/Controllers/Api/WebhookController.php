<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Log, DB};
use App\Models\{User, Subscription, Transaction, Package};
use Stripe\{Webhook, Stripe};

class WebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        // 1. Setup & Verification
        Stripe::setApiKey(config('services.stripe.secret'));
        $payload   = $request->getContent();
        $sigHeader = $request->server('HTTP_STRIPE_SIGNATURE');
        $secret    = config('services.stripe.webhook_secret'); // Changed from env() to config() for safety

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid payload: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid signature: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $data = $event->data->object;

        // 2. The Router (Switch Case)
        switch ($event->type) {

            // --- SCENARIO A: MONTHLY RENEWALS ---
            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($data);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($data);
                break;

            // --- SCENARIO B: ONE-TIME EVENTS ---
            case 'payment_intent.succeeded':
                $this->handlePaymentIntentSucceeded($data);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentIntentFailed($data);
                break;

            // --- SCENARIO C: SUBSCRIPTION LIFECYCLE ---
            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($data);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($data);
                break;

            // --- OPTIONAL: CHECKOUT SESSION (If you use Stripe Hosted Pages) ---
            case 'checkout.session.completed':
                Log::info('Checkout session completed', ['id' => $data->id]);
                break;

            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($data);
                break;

            default:
                Log::info("Unhandled Stripe event: {$event->type}");
                break;
        }

        return response()->json(['status' => 'success']);
    }

    // =================================================================
    //  THE HANDLER LOGIC (Where the DB updates happen)
    // =================================================================

    /**
     * 1. RECURRING SUCCESS
     * Fires every month when the card is successfully charged.
     */
    protected function handleInvoicePaymentSucceeded($invoice)
    {
        if (!$invoice->subscription) return;

        $sub = Subscription::where('stripe_id', $invoice->subscription)->first();

        if ($sub) {
            // Extend the end date by 1 month (or match Stripe's period)
            // Stripe invoices usually have 'period_end' timestamp
            $newEndDate = isset($invoice->lines->data[0]->period->end)
                ? \Carbon\Carbon::createFromTimestamp($invoice->lines->data[0]->period->end)
                : now()->addMonth();

            $sub->update([
                'status' => 'active',
                'ends_at' => $newEndDate
            ]);

            Log::info("Webhook: Subscription {$sub->stripe_id} renewed until {$newEndDate}");
        }
    }

    /**
     * 2. RECURRING FAILURE
     * Fires if the card is declined during renewal.
     */
    protected function handleInvoicePaymentFailed($invoice)
    {
        if (!$invoice->subscription) return;

        $sub = Subscription::where('stripe_id', $invoice->subscription)->first();

        if ($sub) {
            $sub->update(['status' => 'past_due']);
            Log::warning("Webhook: Subscription {$sub->stripe_id} payment failed.");

            // Optional: Send email to user "Please update your card"
        }
    }

    /**
     * 3. ONE-TIME SUCCESS (Per Event)
     * Fires when a PaymentIntent (Single Charge) succeeds.
     */
    protected function handlePaymentIntentSucceeded($intent)
    {
        // We stored metadata in the Controller: ['event_id' => 5, 'user_id' => 1]
        // This is how we know WHAT they bought.
        if (isset($intent->metadata->event_id) && isset($intent->metadata->user_id)) {

            // Check if access already exists (Idempotency)
            $exists = DB::table('event_access')
                ->where('user_id', $intent->metadata->user_id)
                ->where('event_id', $intent->metadata->event_id)
                ->exists();

            if (!$exists) {
                DB::table('event_access')->insert([
                    'user_id'    => $intent->metadata->user_id,
                    'event_id'   => $intent->metadata->event_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info("Webhook: Access granted for Event #{$intent->metadata->event_id}");
            }
        }
    }

    /**
     * 4. ONE-TIME FAILURE
     */
    protected function handlePaymentIntentFailed($intent)
    {
        Log::error("Webhook: PaymentIntent {$intent->id} failed.");
        // Usually nothing to do here locally, maybe log it for analytics
    }

    /**
     * 5. CANCELLATION
     * Fires when a subscription is canceled (by user or admin dashboard)
     */
    protected function handleSubscriptionDeleted($stripeSub)
    {
        $sub = Subscription::where('stripe_id', $stripeSub->id)->first();
        if ($sub) {
            $sub->update([
                'status' => 'canceled',
                'ends_at' => now() // End access immediately
            ]);
            Log::info("Webhook: Subscription {$sub->stripe_id} canceled.");
        }
    }

    /**
     * 6. UPDATES (Upgrades/Downgrades)
     */
    protected function handleSubscriptionUpdated($stripeSub)
    {
        $sub = Subscription::where('stripe_id', $stripeSub->id)->first();
        if ($sub) {
            $sub->update([
                'status' => $stripeSub->status, // e.g. 'active', 'trialing'
            ]);
        }
    }

    protected function handleCheckoutSessionCompleted($session)
    {
        $userId = $session->client_reference_id;

        if (!$userId && isset($session->customer_details->email))
        {
            $user = User::where('email', $session->customer_details->email)->first();
            $userId = $user ? $user->id : null;
        }

       if ($userId)
       {
            Log::info("Webhook: Checkout session completed for User #{$userId}. Session ID: {$session->id}");
            return;
       }

       $packageId = $session->metadata->package_id ?? null;
       $eventId = $session->metadata->event_id ?? null;

       if ($packageId)
       {
            $amountPaid = $session->amount_total/100;
            $package = Package::where('price', $amountPaid)->first();
            $packageId = $package ? $package->id : null;
       }

       if ($session->mode === 'subscription')
       {
            $subscriptionId = $session->subscription;
            Subscription::updateOrCreate(['user_id' => $userId],
            [
                'package_id' => $packageId,
                'stripe_id' => $session->subscription,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ]);
            
            $txnId = $subscriptionId;
       }

       else {

            // --- ONE-TIME PAYMENT ---
            $paymentIntentId = $session->payment_intent;
            
            // Grant Event Access if applicable
            if ($eventId) {
                // Idempotency check
                $exists = DB::table('event_access')
                    ->where('user_id', $userId)
                    ->where('event_id', $eventId)
                    ->exists();

                if (!$exists) {
                    DB::table('event_access')->insert([
                        'user_id'    => $userId,
                        'event_id'   => $eventId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            $txnId = $paymentIntentId;
       }

       // 4. Log Transaction
        Transaction::create([
            'user_id'           => $userId,
            'package_id'        => $packageId ?? 1, // Default fallback
            'stripe_payment_id' => $txnId,
            'amount'            => $session->amount_total / 100,
            'currency'          => $session->currency,
            'status'            => 'paid',
            'type'              => $session->mode,
            'meta_data'         => json_encode([
                'source' => 'checkout_session',
                'event_id' => $eventId
            ])
        ]);

        Log::info("Webhook: Checkout Session completed for User {$userId}");



    }  
}
