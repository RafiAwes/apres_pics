<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use App\Models\{Package, Subscription};
use App\Traits\ApiResponseTraits;

class SubsccriptionController extends Controller
{
    use ApiResponseTraits;

    public function purchase(Request $request)
    {
        $data = $request->validate([
            'package_id' => 'required|exists:packages,id',
            'payment_method_id' => 'required|string',
            'event_id' => 'nullable|exists:events,id',
        ]);

        $user = Auth::guard('api')->user();
        $package = Package::findOrFail($data['package_id']);

        if ($package->type === 'per_event' && empty($data['event_id'])) {
            return $this->errorResponse('Event ID is required for per_event packages', 422);
        }

        if ($package->type === 'monthly' && $user->hasActiveSubscription()) {
            return $this->errorResponse('You already have an active subscription', 409);
        }

        $paymentSuccess = true;

        if (!$paymentSuccess) {
            return $this->errorResponse('Payment failed. Please try again.', 402);
        }

        DB::beginTransaction();
        try {
            if ($package->type === 'monthly') {
                Subscription::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays($package->duration_days),
                    'status' => 'active',
                ]);
            } 
            elseif ($package->type === 'per_event') {
                DB::table('event_access')->insert([
                    'user_id' => $user->id,
                    'event_id' => $data['event_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::commit();
            return $this->successResponse(null,'Subscription successful.', 200, );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Subscription failed. Please try again.', 500, $e->getMessage());
            }
    }
}