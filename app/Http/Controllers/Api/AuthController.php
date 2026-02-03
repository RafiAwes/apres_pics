<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTraits;
use App\Http\Controllers\Controller;
use App\Services\VerificationService;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\{Auth, Hash, Validator};

class AuthController extends Controller
{
    
    use ApiResponseTraits;

    protected  $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    //registration function
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ]);

        $matchPassword = $data['password'] === $data['password_confirmation'];
        if (! $matchPassword) {
            throw ValidationException::withMessages([
                'password' => 'The password does not match.',
            ]);
        }

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->role = 'user';
        $user->save();

        //verify email by sending otp (service)
        $this->verificationService->sendOtp($user);

        return $this->successResponse(['user' => $user], 'User registered successfully. Please verify your email with the OTP sent.', 201);

    }

    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'otp' => 'required|string|max:6',
        ]);

        $user = User::where('email', $data['email'])->first();

        if(!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        $result = $this->verificationService->verifyOtp($user, $data['otp']);

        if($result['success']) {
            $user->email_verified_at = now();
            $user->otp = null;
            $user->otp_expires_at = null;
            $user->save();

            return $this->successResponse(null, 'Email verified successfully.', 200);
        } else {
            return $this->errorResponse($result['message'], 400);
        }
    }

    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $user = User::where('email', $data['email'])->first();

        if(!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        $result = $this->verificationService->resendOtp($user);

        if($result['success']) {
            return $this->successResponse(null, $result['message'], 200);
        } else {
            return $this->errorResponse($result['message'], 400);
        }
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $user = User::where('email', $data['email'])->first();

        if(!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        $result = $this->verificationService->forgotPassword($user);

        return $this->successResponse(null, $result['message'], 200);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255',
            'otp' => 'required|string|max:6',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ]);

        $user = User::where('email', $data['email'])->first();

        if(!$user) {
            return $this->errorResponse('User not found.', 404);
        }

        $result = $this->verificationService->resetPassword($user, $data['otp'], $data['password']);

        if($result['success']) {
            return $this->successResponse(null, $result['message'], 200);
        } else {
            return $this->errorResponse($result['message'], 400);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return $this->errorResponse('Invalid credentials.', 401);
        }

        $user = Auth::guard('api')->user();
        
        // Check if user is banned and ban hasn't expired
        if ($user->ban_expires_at && $user->ban_expires_at->isFuture()) {
            Auth::guard('api')->logout();
            return $this->errorResponse(
                'Your account is banned until ' . $user->ban_expires_at->toDateTimeString() . '. Reason: ' . $user->ban_reason, 
                403
            );
        }
        
        // If ban has expired, clear the ban
        if ($user->ban_expires_at && $user->ban_expires_at->isPast()) {
            $user->update([
                'ban_type' => null,
                'ban_expires_at' => null,
                'ban_reason' => null,
            ]);
        }

        if (!$user->email_verified_at) {
            Auth::guard('api')->logout();
            return $this->errorResponse('Email is not verified. Please verify your email before logging in.', 403);
        }
        
        return $this->RespondWithToken($token, $user);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->successResponse(null, 'Successfully logged out.', 200);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string|min:8',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
        ]);

        $user = Auth::guard('api')->user();

        if (!$user) {
            return $this->errorResponse('User not authenticated.', 401);
        }
        // Verify current password
        if (!Hash::check($data['current_password'], $user->password)) {
            return $this->errorResponse('Current password is incorrect.', 400);
        }

        // Check if new password is different from current
        if (Hash::check($data['password'], $user->password)) {
            return $this->errorResponse('New password must be different from current password.', 400);
        }

        // Update password
        $user->password = Hash::make($data['password']);
        $user->save();

        return $this->successResponse(null, 'Password changed successfully.', 200);
    }
    
    
    
    
    
    
    // private functions 
    private function RespondWithToken($token, $user)
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60, 
            'user' => $user
        ], 'Authenticated successfully.', 200);
    }
}