<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use App\Http\Controllers\Controller;
use App\Models\{Package, Subscription};
use App\Traits\ApiResponseTraits;

class SubscriptionController extends Controller
{
    use ApiResponseTraits;

    public function purchases(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'payment_method_id' => 'required|string',
            'event_id' => 'required|exists:events,id',
        ]);

        $user = Auth::guard('api')->user();
        $package = Package::find($request->package_id);

        if ($package->type === 'per_event' && ! $request->event_id) {
            return $this->errorResponse('Event ID is required for this package.', 422);
        }

        if ($package->type === 'monthly' && $user->hasActiveSubscription()) {
            return $this->errorResponse('You already have an active subscription.', 409);
        }

        $paymentSuccess = true;

        if (! $paymentSuccess) {
            return $this->errorResponse('Payment failed. Please try again.', 402);
        }

        DB::beginTransaction();
        try {
            if ($package->type === 'monthly') {
                Subscription::updateOrCreate([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'starts_at' => now(),
                    'expires_at' => now()->addMonth(),
                    'is_active' => true,
                ]);
            }
            elseif ($package->type === 'per_event') {
                // Assuming you check if they already bought this event
                DB::table('event_access')->insert([
                    'user_id' => $user->id,
                    'event_id' => $request->event_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::commit();
            return $this->successResponse(null, 'Purchase successful. Access granted.', 200);
        } catch (\Exception $e) {   
            DB::rollBack();
            return $this->errorResponse('An error occurred while processing your purchase.', 500);
        }
    }
}
