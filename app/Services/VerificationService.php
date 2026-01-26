<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Notifications\SendOtpMail;

class VerificationService
{
    protected const EXPIRATION_TIME = 300; // 5 minutes

    private function generateOtp(): string
    {
        $otp = random_int(100000, 999999);
        return $otp;
    }

    public function sendOtp(User $user): array
    {
        $generate = $this->generateOtp();
        $otp = Hash::make($generate);
        

        $user->update([
            "otp"=> $otp,
            "otp_expires_at" => now()->addSeconds(self::EXPIRATION_TIME),
        ]);

        $user->notify(new SendOtpMail($generate));

        return ['success' => true, 'message' => 'OTP sent successfully.' ];
    }

    public function verifyOtp(User $user, string $otp)  
    {
        if(now()->greaterThan($user->otp_expires_at)) {
            return [
                'success' => false,
                'message' => 'OTP has expired.',
            ];
        }

        if($user->email_verified_at !== null) {
            return [
                'success' => false,
                'message' => 'Email is already verified.',
            ];
        }
        
        if($user->otp !== null) {
            $storedOtp = $user->otp;

        } else {
            return [
                'success' => false,
                'message' => 'No OTP found for this user.',
            ];
        }
        if($storedOtp && Hash::check($otp, $storedOtp)) {
                // Invalidate OTP after successful verification

                $user->update([
                    "otp" => null,
                    "otp_expires_at" => null,
                ]);

                return [
                    'success' => true,
                    'message' => 'OTP verified successfully.',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid OTP.',
                ];
            }
    }

    public function resendOtp(User $user): array
    {
        if($user->email_verified_at !== null) {
            return [
                'success' => false,
                'message' => 'Email is already verified.',
            ];
        }

        return $this->sendOtp($user);
    }

    public function forgotPassword(User $user): array
    {
        $generate = $this->generateOtp();
        $otp = Hash::make($generate);

        $user->update([
            "otp" => $otp,
            "otp_expires_at" => now()->addSeconds(self::EXPIRATION_TIME),
        ]);

        $user->notify(new SendOtpMail($generate));

        return ['success' => true, 'message' => 'Password reset OTP sent to your email.'];
    }

    public function resetPassword(User $user, string $otp, string $password): array
    {
        if(now()->greaterThan($user->otp_expires_at)) {
            return [
                'success' => false,
                'message' => 'OTP has expired.',
            ];
        }

        if($user->otp === null) {
            return [
                'success' => false,
                'message' => 'No OTP found for this user.',
            ];
        }

        if(!Hash::check($otp, $user->otp)) {
            return [
                'success' => false,
                'message' => 'Invalid OTP.',
            ];
        }

        $user->update([
            "password" => Hash::make($password),
            "otp" => null,
            "otp_expires_at" => null,
        ]);

        return [
            'success' => true,
            'message' => 'Password reset successfully.',
        ];
    }
}