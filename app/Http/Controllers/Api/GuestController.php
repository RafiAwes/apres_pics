<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Services\GuestService;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    use ApiResponseTraits;

    protected $guestService;

    public function __construct(GuestService $guestService)
    {
        $this->guestService = $guestService;
    }

    public function sendInvitation(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string',
            'email' => 'required|email',
            'event_id' => 'required|exists:events,id',
        ]);

        try {
            $guest = $this->guestService->findOrCreateGuest(
                $request->email,
                $request->event_id,
                $request->name
            );

            $link = $this->guestService->generateLink($guest);
            $otp = $this->guestService->generateOtp($guest);

            $this->guestService->sendInviteEmail($request->email, $link, $otp);

            return $this->successResponse(['guest_id' => $guest->id, 'message' => 'Invitation sent successfully'], 'Email sent.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function validateLink(Request $request, $guestId, $eventId)
    {
        if (! $request->hasValidSignature()) {
            return $this->errorResponse('Invalid or Expired Link', 403);
        }

        return $this->successResponse([
            'event_id' => $eventId,
            'guest_id' => $guestId,
        ], 'Link Valid. Please enter OTP.', 200);

    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'guest_id' => 'required',
            'otp' => 'required|numeric',
        ]);

        $guest = Guest::where('id', $request->guest_id)->first();

        if (! $guest || $guest->otp_code !== $request->otp) {
            return $this->errorResponse('Invalid OTP.', 401);
        }

        if (now()->greaterThan($guest->otp_expires_at)) {
            return $this->errorResponse('OTP expired.', 401);
        }

        // Generate Access Token (Simple version)
        $token = bin2hex(random_bytes(32));
        $guest->update([
            'access_token' => $token,
            'otp_code' => null, // Burn the OTP
        ]);

        return $this->successResponse([
            'access_token' => $token,
        ], 'Login Successful.', 200);
    }
}
