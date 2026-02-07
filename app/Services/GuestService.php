<?php

namespace App\Services;

use Illuminate\Support\Facades\{Mail, URL};
use App\Models\Guest;
use App\Mail\InviteGuestMail;
use Carbon\Carbon;

class GuestService
{
    public function findOrCreateGuest($email, $eventId, $name = null)
    {
        return Guest::firstOrCreate(
            ['email' => $email, 'event_id' => $eventId],
            ['name' => $name]
        ); 
    }

    public function generateLink(Guest $guest)
    {
         $expiresAt = now()->addMinutes(config('app.guest_link_expiry', 60));
        return URL::temporarySignedRoute(
            'guest.view.event',
            $expiresAt,
            ['guestId' => $guest->id, 'eventId' => $guest->event_id]
        );
    }

    public function generateOtp(Guest $guest)
    {
        $otp = rand(100000, 999999);
        
        $guest->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(30)
        ]);

        return $otp;
    }

    public function sendInviteEmail($email, $link, $otp)
    {
        Mail::to($email)->send(new InviteGuestMail($link, $otp));
        return true;
    }
}