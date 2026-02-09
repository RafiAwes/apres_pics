<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log, Validator};
use App\Models\{Package, Subscription, Transaction};
use App\Traits\ApiResponseTraits;
use Stripe\{Stripe, PaymentIntent};

class SubscriptionController extends Controller
{
    use ApiResponseTraits;

    public function __construct()
    {
        // Set Stripe API Key
        // Checks config, then STRIPE_SECRET, then STRIPE_SECRET_KEY (common variation)
        $apiKey = config('services.stripe.secret') ?? env('STRIPE_SECRET') ?? env('STRIPE_SECRET_KEY');
        
        if (!$apiKey) {
            throw new \Exception('Stripe API Key not found. Please set STRIPE_SECRET in .env');
        }
        
        Stripe::setApiKey($apiKey);
    }

    /**
     * STEP 1: Create Payment Intent
     * Returns client_secret to frontend
     */
    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'payment_method_id' => 'nullable|string', // Optional: for API-only testing/payment
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        try {
            $package = Package::findOrFail($request->package_id);
            // Amount in cents
            $amountInCents = round($package->price * 100);

            $intentData = [
                'amount' => $amountInCents,
                'currency' => 'usd',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'package_id' => $package->id,
                    'user_id' => Auth::id(),
                ],
            ];

            // If payment_method_id is provided, confirm immediately (API-driven flow)
            if ($request->has('payment_method_id')) {
                $intentData['payment_method'] = $request->payment_method_id;
                $intentData['confirm'] = true;
                $intentData['return_url'] = url('/'); // Required for confirm=true
                // Remove automatic_payment_methods if providing manual method often avoids conflicts, 
                // but usually fine if method matches. Safe to unset for explicit cards.
                unset($intentData['automatic_payment_methods']); 
            }

            $paymentIntent = PaymentIntent::create($intentData);

            return $this->successResponse([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status, // Return status so client knows if it succeeded
                'amount' => $package->price,
                'currency' => 'usd',
            ], 'Payment Intent created successfully', 200);

        } catch (\Exception $e) {
            Log::error('Stripe Intent Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to create Payment Intent', 500, $e->getMessage());
        }
    }

    /**
     * STEP 2: Confirm Subscription
     * Call this AFTER frontend confirms payment with Stripe
     */
    public function confirmSubscription(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
            'package_id' => 'required|exists:packages,id',
            'event_id' => 'nullable|exists:events,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Error', 422, $validator->errors());
        }

        $user = Auth::guard('api')->user();
        $package = Package::findOrFail($request->package_id);

        try {
            // Retrieve Intent to verify status
            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            if ($paymentIntent->status !== 'succeeded') {
                return $this->errorResponse('Payment not succeeded yet. Status: ' . $paymentIntent->status, 400);
            }

            // Check if already processed to avoid duplicates
            if (Transaction::where('stripe_payment_id', $paymentIntent->id)->exists()) {
                 return $this->errorResponse('Transaction already processed.', 409);
            }

            return DB::transaction(function () use ($user, $package, $paymentIntent, $request) {
                
                // 1. Create/Record Subscription Access
                if ($package->type === 'monthly') {
                   Subscription::create([
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'type' => 'monthly',
                        'stripe_id' => $paymentIntent->id,
                        'stripe_status' => $paymentIntent->status,
                        'stripe_price' => $package->price,
                        'quantity' => 1,
                        'starts_at' => now(),
                        'ends_at' => now()->addDays($package->duration_days),
                        'status' => 'active',
                    ]);
                } 
                elseif ($package->type === 'per_event') {
                    if (empty($request->event_id)) {
                        throw new \Exception('Event ID required for per_event package.');
                    }
                    
                    DB::table('event_access')->insert([
                        'user_id' => $user->id,
                        'event_id' => $request->event_id,
                        // 'payment_id' => $paymentIntent->id, 
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 2. Log Transaction
                Transaction::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'stripe_payment_id' => $paymentIntent->id,
                    'amount' => $package->price,
                    'currency' => $paymentIntent->currency,
                    'type' => 'subscription', 
                    'status' => 'paid',
                    'meta_data' => [
                        'payment_method' => $paymentIntent->payment_method ?? null,
                        'event_id' => $request->event_id ?? null
                    ],
                ]);

                return $this->successResponse([
                    'package' => $package->name,
                    'status' => 'active'
                ], 'Subscription confirmed and activated.', 200);
            });

        } catch (\Exception $e) {
            Log::error('Subscription Confirmation Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to confirm subscription', 500, $e->getMessage());
        }
    }
}
