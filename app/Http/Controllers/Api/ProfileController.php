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
    
    public function updateUserName(Request $request, $user)
    {
        try {
            $user->update([
                'name' => $request->name,
            ]);
            return $this->successResponse($user, 'User name updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update user name', 500, $e->getMessage() . ' ' . $e->getLine());
        }

    } 

    public function updatePassword(Request $request, $user)
{
    
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
            if ($user->avatar) {
                $this->deleteImage($user->avatar);
            }

            // 4. Update user record
            $user->update(['avatar' => $imagePath]);

            return $this->successResponse($user, 'Avatar updated successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update avatar', 500, $e->getMessage() . ' ' . $e->getLine());
        }
    }
}
