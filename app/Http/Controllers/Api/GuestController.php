<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Services\{EventService, GuestService};
use App\Traits\ApiResponseTraits;
use Firebase\JWT\JWT;

class GuestController extends Controller
{
    use ApiResponseTraits;

    protected $guestService;
    protected $eventService;

    public function __construct(GuestService $guestService)
    {
        $this->guestService = $guestService;
        // EventService injected to generate/set event passwords
        $this->eventService = app(EventService::class);
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

            // Use existing event password if set; otherwise generate and persist one
            $event = $guest->event;
            $password = null;
            if ($event) {
                if ($event->password) {
                    try {
                        $password = $this->eventService->decryptPassword($event);
                    } catch (\Exception $e) {
                        // if decryption fails (corrupt or old hash), generate new password
                        $password = $this->eventService->generatePassword();
                        $this->eventService->setPasswordForEvent($event, $password);
                    }
                } else {
                    $password = $this->eventService->generatePassword();
                    $this->eventService->setPasswordForEvent($event, $password);
                }
            }

            // Send the invite with link and plaintext password when available
            $this->guestService->sendInviteEmail($request->email, $link, $password);

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
        ], 'Link Valid. Please enter password.', 200);

    }

    public function verifyPassword(Request $request)
    {
        $request->validate([
            'guest_id' => 'required',
            'password' => 'required|string',
        ]);

        $guest = Guest::where('id', $request->guest_id)->first();

        if (! $guest) {
            return $this->errorResponse('Guest not found.', 404);
        }

        $event = $guest->event;
        if (! $event) {
            return $this->errorResponse('Event not found.', 404);
        }

        if (! $event->password) {
            return $this->errorResponse('This event is not password protected.', 403);
        }

        try {
            $decrypted = $this->eventService->decryptPassword($event);
        } catch (\Exception $e) {
            return $this->errorResponse('Unable to validate password.', 500);
        }

        if ($request->password !== $decrypted) {
            return $this->errorResponse('Invalid password.', 401);
        }

        $jwtSecret = env('JWT_SECRET', config('app.key'));
        $now = time();
        $payload = [
            'sub' => $guest->id,
            'event_id' => $event->id,
            'iat' => $now,
            'exp' => $now + 60 * 60 * 24,
        ];

        $token = JWT::encode($payload, $jwtSecret, 'HS256');

        $guest->update([
            'access_token' => $token,
        ]);

        return $this->successResponse([
            'access_token' => $token,
        ], 'Login Successful.', 200);
    }
}
