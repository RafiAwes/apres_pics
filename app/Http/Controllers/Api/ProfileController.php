<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\{ApiResponseTraits, ImageTrait};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    use ImageTrait, ApiResponseTraits;

    public function getProfile()
    {
        $user = Auth::user();
        $profile = $user->only(['id', 'name', 'email', 'avatar']);
        return $this->successResponse($profile, 'User profile fetched successfully.', 200);
    }

    public function updateUserName(Request $request)
    {
        $user = Auth::user();
        try {
            $user->update([
                'name' => $request->name,
            ]);
            return $this->successResponse($user, 'User name updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update user name', 500, $e->getMessage() . ' ' . $e->getLine());
        }
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        // 'confirmed' automatically expects a field named 'new_password_confirmation' in the request.
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed|different:current_password',
        ]);

        try {
            // 2. Verification Phase
            // Check if the provided current password matches the one in the database
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->errorResponse('The provided current password does not match our records.', 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return $this->successResponse($user, 'Password updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update password', 500, $e->getMessage() . ' line: ' . $e->getLine());
        }
    }


    public function updateAdminProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'           => 'sometimes|string|max:255',
            'contact_number' => 'sometimes|string|max:20',
            'address'        => 'sometimes|string|max:255',
        ]);

        try {
            $user->update($request->only(['name', 'contact_number', 'address']));

            $profile = $user->only(['id', 'name', 'email', 'avatar', 'contact_number', 'address']);
            return $this->successResponse($profile, 'Admin profile updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update admin profile', 500, $e->getMessage() . ' ' . $e->getLine());
        }
    }

    public function updateAvatar(Request $request)
    {
        $user = Auth::user();

        // 1. Validation
        $rules = [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:12288', // max 12MB
        ];

        $request->validate($rules);

        try {
            // 2. Upload new avatar
            $imagePath = $this->uploadAvatar($request, 'avatar', 'images/user');

            // 3. Delete old avatar if exists
            $currentAvatarPath = $user->getRawOriginal('avatar');
            if ($currentAvatarPath) {
                $this->deleteImage($currentAvatarPath);
            }

            // 4. Update user record
            $user->update(['avatar' => $imagePath]);

            return $this->successResponse($user, 'Avatar updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update avatar', 500, $e->getMessage() . ' ' . $e->getLine());
        }
    }
}
