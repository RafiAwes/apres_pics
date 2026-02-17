<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log, Validator};
use Stripe\{Stripe, Customer, Subscription, PaymentMethod, PaymentIntent};
use App\Models\{Package, Transaction, User, Subscription as LocalSubscription};
use Stripe\Checkout\Session;

use App\Traits\ApiResponseTraits;

class SubscriptionController extends Controller
{
    use ApiResponseTraits;
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * UNIFIED PAYMENT API
     */
    public function createPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id'     => 'required|exists:packages,id',
            'payment_method' => 'required|string',
            'event_id'       => 'nullable|exists:events,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        $user = Auth::user();
        // return $user->id; 
        $package = Package::findOrFail($request->package_id);

        DB::beginTransaction();
        try {
            // --- SCENARIO A: MONTHLY SUBSCRIPTION ---
            if ($package->type === 'monthly') {

                $transaction_type = 'subscription'; 
                // 1. Create/Get Customer
                if (!$user->stripe_id) {
                    $customer = Customer::create([
                        'email' => $user->email,
                        'name'  => $user->name,
                        'payment_method' => $request->payment_method,
                        'invoice_settings' => ['default_payment_method' => $request->payment_method],
                    ]);
                    $user->update(['stripe_id' => $customer->id]);
                    $customerId = $customer->id;
                } else {
                    $customerId = $user->stripe_id;
                    $pm = PaymentMethod::retrieve($request->payment_method);
                    $pm->attach(['customer' => $customerId]);
                    Customer::update($customerId, [
                        'invoice_settings' => ['default_payment_method' => $request->payment_method]
                    ]);
                }

                // 2. Create Subscription
                $stripeSub = Subscription::create([
                    'customer' => $customerId,
                    'items' => [['price' => $package->stripe_price_id]],
                    'expand' => ['latest_invoice.payment_intent'],
                ]);

                Log::info('Stripe Subscription Created', ['sub_id' => $stripeSub->id, 'status' => $stripeSub->status]);
                Log::info('Latest Invoice Dump', ['invoice' => $stripeSub->latest_invoice]);

                if ($stripeSub->status === 'active') {
                    // Create Local Record
                    LocalSubscription::create([
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'stripe_id' => $stripeSub->id,
                        'status' => 'active',
                        'starts_at' => now(),
                        'ends_at' => now()->addMonth()
                    ]);

                    $msg = "Subscription active.";
                    // Safely get transaction ID
                    if (isset($stripeSub->latest_invoice) && isset($stripeSub->latest_invoice->payment_intent)) {
                        $txnId = $stripeSub->latest_invoice->payment_intent->id;
                    } else {
                        // Fallback if no payment intent (e.g. trial or zero amount)
                        $txnId = $stripeSub->latest_invoice->id ?? $stripeSub->id;
                    }
                } else {
                    DB::rollBack();
                    return $this->successResponse([
                        'requires_action' => true,
                        'client_secret' => $stripeSub->latest_invoice->payment_intent->client_secret
                    ], 'Payment requires action', 200);
                }
            }

            // --- SCENARIO B: ONE-TIME PAYMENT ---
            elseif ($package->type === 'per_event') {
                if (!$request->event_id) return $this->errorResponse('Event ID required', 422);

                $transaction_type = 'one_time';

                $intent = PaymentIntent::create([
                    'amount' => round($package->price * 100),
                    'currency' => 'usd',
                    'payment_method' => $request->payment_method,
                    'confirm' => true,
                    'return_url' => 'http://localhost/dummy',
                    'metadata' => ['event_id' => $request->event_id, 'package_id' => $package->id, 'user_id' => $user->id]
                ]);

                if ($intent->status === 'succeeded') {
                    DB::table('event_access')->insert([
                        'user_id' => $user->id,
                        'event_id' => $request->event_id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $msg = "Event unlocked.";
                    $txnId = $intent->id;
                } else {
                    DB::rollBack();
                    return $this->successResponse([
                        'requires_action' => true,
                        'client_secret' => $intent->client_secret
                    ], 'Payment requires action', 200);
                }
            }

            // Log Transaction
            Transaction::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'stripe_payment_id' => $txnId,
                'amount' => $package->price,
                'status' => 'paid',
                'meta_data' => json_encode(['event_id' => $request->event_id ?? null])
            ]);

            DB::commit();
            return $this->successResponse(null, $msg, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function createCheckoutSession(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        // 1. Dynamic Amount (e.g., from request or database)
        $amount = $request->input('amount'); // 45.50
        $packageName = "Custom Package";

        // 2. Create the Session
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $packageName,
                    ],
                    'unit_amount' => round($amount * 100), // Convert to cents (4550)
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment', // use 'subscription' if recurring

            // 3. Metadata is CRUCIAL for the Webhook
            'client_reference_id' => Auth::id(), // User ID
            'metadata' => [
                'package_id' => $request->package_id,
                'custom_note' => 'Variable amount charge'
            ],

            'success_url' => 'http://localhost:3000/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => 'http://localhost:3000/cancel',
        ]);

        // 4. Return the URL to the frontend
        return response()->json([
            'url' => $session->url
        ]);
    }
}
