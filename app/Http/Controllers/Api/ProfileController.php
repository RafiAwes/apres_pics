<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Traits\{ApiResponseTraits, ImageTrait};

class ProfileController extends Controller
{
    use ImageTrait, ApiResponseTraits;
    
    public function updateUserName(Request $request)
    {
        
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
