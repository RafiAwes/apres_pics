<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Notifications\SendOtpMail;
use App\Traits\ApiResponseTraits;

class VerificationService
{
    use ApiResponseTraits;
    protected const EXPIRATION_TIME = 300; // 5 minutes

    private function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function sendOtp(User $user): JsonResponse
    {
        $generate = $this->generateOtp();
        $otp = Hash::make($generate);


        $user->update([
            "otp" => $otp,
            "otp_expires_at" => now()->addSeconds(self::EXPIRATION_TIME),
            "otp_verified" => false,
            "otp_verified_at" => null,
        ]);

        $user->notify(new SendOtpMail($generate));

        return $this->successResponse(null, 'OTP sent successfully.', 200);
    }

    public function verifyOtp(User $user, string $otp): JsonResponse
    {
        // Determine flow: If email is not verified, it's registration flow. 
        // If email is verified, it's forgot password flow.

        if ($user->email_verified_at === null) {
            // Registration/Email Verification flow
            if ($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
                return $this->errorResponse('OTP has expired.', 400);
            }

            if ($user->otp === null) {
                return $this->errorResponse('No OTP found for this user.', 400);
            }

            if (Hash::check($otp, $user->otp)) {
                $user->update([
                    "otp" => null,
                    "otp_expires_at" => null,
                    "otp_verified" => 1,
                    "otp_verified_at" => now(),
                    "email_verified_at" => now(),
                ]);

                return $this->successResponse(null, 'Email verified successfully.', 200);
            }
        } else {
            // Forgot Password flow
            $resetRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();

            if (!$resetRecord) {
                return $this->errorResponse('No password reset OTP found for this user.', 400);
            }

            // Check expiration (standard Laravel password reset doesn't have a per-record expiration by default in this table, 
            // but we can check created_at if we want to enforce it manually, e.g., 5 mins)
            if (now()->diffInSeconds($resetRecord->created_at) > self::EXPIRATION_TIME) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                return $this->errorResponse('OTP has expired.', 400);
            }

            if (Hash::check($otp, $resetRecord->token)) {
                $user->update([
                    'otp_verified' => true,
                    'otp_verified_at' => now(),
                ]);
                return $this->successResponse(null, 'OTP verified successfully. You can now reset your password.', 200);
            }
        }

        return $this->errorResponse('Invalid OTP.', 400);
    }

    public function resendOtp(User $user): JsonResponse
    {
        if ($user->email_verified_at !== null) {
            return $this->forgotPassword($user);
        }

        return $this->sendOtp($user);
    }

    public function forgotPassword(User $user): JsonResponse
    {
        $generate = $this->generateOtp();
        $otp = Hash::make($generate);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $otp,
                'created_at' => now(),
            ]
        );

        $user->notify(new SendOtpMail($generate));

        return $this->successResponse(null, 'Password reset OTP sent to your email.', 200);
    }

    public function resetPassword(User $user, string $password): JsonResponse
    {
        if (!$user->otp_verified || now()->diffInSeconds($user->otp_verified_at) > self::EXPIRATION_TIME) {
            return $this->errorResponse('Password reset OTP verification required before resetting password.', 400);
        }

        $resetRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();

        if (!$resetRecord) {
            return $this->errorResponse('Password reset OTP verification required before resetting password.', 400);
        }

        // Update password and clear record
        $user->update([
            "password" => Hash::make($password),
            'otp_verified' => false,
            'otp_verified_at' => null,
        ]);

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return $this->successResponse(null, 'Password reset successfully.', 200);
    }
}
