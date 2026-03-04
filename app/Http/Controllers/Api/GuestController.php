<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $this->eventService = app(EventService::class);
    }

    public function sendInvitation(Request $request)
    {
        $request->validate([
            'emails' => 'required|array',
            'emails.*' => 'email',
            'event_id' => 'required|exists:events,id',
            'link' => 'required|string',
        ]);

        try {
            $eventId = $request->event_id;
            $link = $request->link;
            $emails = $request->emails;

            $event = \App\Models\Event::findOrFail($eventId);
            if ($event->user_id !== Auth::id()) {
                return $this->errorResponse('You are not authorized to invite guests to this event.', 403);
            }

            $password = null;
            if ($event->password) {
                try {
                    $password = $this->eventService->decryptPassword($event);
                } catch (\Exception $e) {

                    $password = null;
                }
            }

            foreach ($emails as $email) {
                $guest = $this->guestService->findOrCreateGuest($email, $eventId);

                // Send the invite with provided link and event password
                $this->guestService->sendInviteEmail($email, $link, $password, $event->name);
            }

            return $this->successResponse(null, 'Invitations sent successfully to ' . count($emails) . ' guests.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function editEmail(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|exists:guests,id',
            'email' => 'required|email',
        ]);

        try {
            $guest = Guest::findOrFail($request->guest_id);

            // Check event ownership
            if ($guest->event->user_id !== Auth::id()) {
                return $this->errorResponse('You are not authorized to manage this guest.', 403);
            }

            // Check if another guest in the same event already has this email
            $duplicate = Guest::where('event_id', $guest->event_id)
                ->where('email', $request->email)
                ->where('id', '!=', $guest->id)
                ->first();

            if ($duplicate) {
                return $this->errorResponse('This email is already registered as a guest for this event.', 422);
            }

            $guest->email = $request->email;
            $guest->save();

            return $this->successResponse($guest, 'Guest email updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function sendAgain(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|exists:guests,id',
            'link' => 'required|string',
        ]);

        try {
            $guest = Guest::findOrFail($request->guest_id);
            $event = $guest->event;

            // Check event ownership
            if ($event->user_id !== Auth::id()) {
                return $this->errorResponse('You are not authorized to manage this guest.', 403);
            }

            $password = null;
            if ($event && $event->password) {
                $password = $this->eventService->decryptPassword($event);
            }

            $this->guestService->sendInviteEmail($guest->email, $request->link, $password, $event->name);

            return $this->successResponse(null, 'Invitation resent successfully.', 200);
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
    public function deleteGuest($id)
    {
        try {
            $guest = Guest::findOrFail($id);

            if ($guest->event->user_id !== Auth::id()) {
                return $this->errorResponse('You are not authorized to manage this guest.', 403);
            }

            $guest->delete();

            return $this->successResponse(null, 'Guest deleted successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
