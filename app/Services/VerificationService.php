<?php

namespace App\Services;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Notifications\SendOtpMail;
use App\Traits\ApiResponseTraits;

class VerificationService
{
    use ApiResponseTraits;
    protected const EXPIRATION_TIME = 300; // 5 minutes

    private function generateOtp(): string
    {
        $otp = random_int(100000, 999999);
        return $otp;
    }

    public function sendOtp(User $user): JsonResponse
    {
        $generate = $this->generateOtp();
        $otp = Hash::make($generate);
        

        $user->update([
            "otp"=> $otp,
            "otp_expires_at" => now()->addSeconds(self::EXPIRATION_TIME),
            "otp_verified" => false,
            "otp_verified_at" => null,
        ]);

        $user->notify(new SendOtpMail($generate));

        return $this->successResponse(null, 'OTP sent successfully.', 200);
    }

    public function verifyOtp(User $user, string $otp): JsonResponse
    {
        if($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
            return $this->errorResponse('OTP has expired.', 400);
        }

        if($user->email_verified_at !== null) {
            return $this->errorResponse('Email is already verified.', 400);
        }
        
        if($user->otp !== null) {
            $storedOtp = $user->otp; 

        } else {
            return $this->errorResponse('No OTP found for this user.', 400);
        }
        if($storedOtp && Hash::check($otp, $storedOtp)) {
            $user->update([
                "otp" => null,
                "otp_expires_at" => null,
                "otp_verified" => 1,
                "otp_verified_at" => now(),
                "email_verified_at" => now(),
            ]);

            return $this->successResponse(null, 'OTP verified successfully.', 200);
        } else {
            return $this->errorResponse('Invalid OTP.', 400);
        }
    }

    public function resendOtp(User $user): JsonResponse
    {
        if($user->otp_verified_at !== null) {
            return $this->errorResponse('Email is already verified.', 400);
        }

        return $this->sendOtp($user);
    }

    public function forgotPassword(User $user): JsonResponse
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

        return $this->successResponse(null, 'Password reset OTP sent to your email.', 200);
    }

    public function forgotPasswordVerify(User $user, string $otp): JsonResponse
    {
        if($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
            return $this->errorResponse('OTP has expired.', 400);
        }

        if($user->otp !== null) {
            $storedOtp = $user->otp; 

        } else {
            return $this->errorResponse('No OTP found for this user.', 400);
        }
        if($storedOtp && Hash::check($otp, $storedOtp)) {
            $user->update([
                "otp_verified" => 1,
                "otp_verified_at" => now(),
            ]);
            return $this->successResponse(null, 'OTP verified successfully. You can now reset your password.', 200);
        } else {
            return $this->errorResponse('Invalid OTP.', 400);
        }
    }

   public function resetPassword(User $user, string $password): JsonResponse
    {
        if (!$user->otp_verified || $user->otp_verified_at === null || $user->otp_expires_at === null) {
            return $this->errorResponse('Forgot password OTP verification required before resetting password.', 400);
        }

        if($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
            return $this->errorResponse('OTP has expired.', 400);
        }

        // Update password and clear OTP
        $user->update([
            "password" => Hash::make($password),
            "otp" => null,
            "otp_expires_at" => null,
            "otp_verified" => false,
            "otp_verified_at" => null,
        ]);
        
        return $this->successResponse(null, 'Password reset successfully.', 200);
    }
}