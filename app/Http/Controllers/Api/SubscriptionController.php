<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use App\Models\{Package, Subscription, Transaction};
use App\Traits\ApiResponseTraits;
use App\Http\Controllers\Controller;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController extends Controller
{
    use ApiResponseTraits;

    public function purchase(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'payment_method_id' => 'required|string',
            'event_id' => 'nullable|integer',
        ]);

        $user = Auth::guard('api')->user();
        $package = Package::find($request->package_id);

        try {
            if (! $user->hasStripeId()) {
                $user->createAsStripeCustomer();
            }

            // === SCENARIO 1: MONTHLY SUBSCRIPTION ===
            if ($package->type === 'monthly') {
                if ($user->subscribed('default')) {
                    return $this->errorResponse('You already have an active subscription.', 409);
                }

                // Create Stripe Subscription
                $subscription = $user->newSubscription('default', $package->stripe_price_id)
                    ->create($request->payment_method_id);

                // LOG THE TRANSACTION
                Transaction::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'stripe_payment_id' => $subscription->latestInvoice()->id, // Capture Invoice ID
                    'amount' => $package->price,
                    'type' => 'subscription',
                    'status' => 'succeeded',
                    'meta_data' => ['stripe_sub_id' => $subscription->id],
                ]);

                return $this->successResponse(null, 'Subscription started successfully!', 200);
            }

            // === SCENARIO 2: PAY PER EVENT ===
            elseif ($package->type === 'per_event') {
                if (! $request->event_id) {
                    return $this->errorResponse('Event ID is required.', 422);
                }

                $amountInCents = $package->price * 100;

                // Charge Stripe
                $charge = $user->charge($amountInCents, $request->payment_method_id, [
                    'description' => 'Access to Event ID: '.$request->event_id,
                ]);

                // DB Transaction to ensure we don't take money without giving access
                DB::transaction(function () use ($user, $package, $charge, $request) {

                    // 1. Grant Access
                    DB::table('event_access')->insert([
                        'user_id' => $user->id,
                        'event_id' => $request->event_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // 2. LOG THE TRANSACTION
                    Transaction::create([
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'stripe_payment_id' => $charge->id, // Capture Charge ID
                        'amount' => $package->price,
                        'type' => 'one_time',
                        'status' => 'succeeded',
                        'meta_data' => ['event_id' => $request->event_id],
                    ]);
                });

                return $this->successResponse(null, 'Event access purchased successfully!', 200);
            }

        } catch (IncompletePayment $e) {
            // Handle 3D Secure / Confirmation
            return $this->errorResponse('Payment authentication required.', 402);
        } catch (\Exception $e) {
            return $this->errorResponse('Payment failed: '.$e->getMessage(), 500);
        }
    }
}
