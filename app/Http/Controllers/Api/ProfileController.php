<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\{Auth, Hash};
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Traits\{ApiResponseTraits, ImageTrait};

class ProfileController extends Controller
{
    use ImageTrait, ApiResponseTraits;
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // 1. Validation (All nullable so partial updates work)
        $rules = [
            'name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ];

        $request->validate($rules);

        try {
            // 2. Smart Update: Only update fields present in the request
            // $request->filled('key') returns true only if key is present AND not empty/null
            if($user->role === 'admin'){
                if ($request->filled('name')) {
                $user->name = $request->name;
                }

                if ($request->filled('contact_number')) {
                    $user->contact_number = $request->contact_number;
                }

                if ($request->filled('address')) {
                    $user->address = $request->address;
                }
            }
            else{
                if ($request->filled('name')) {
                    $user->name = $request->name;
                }
            }

            $user->save();

        return $this->successResponse($user, 'Profile info updated successfully', 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update profile info', 500, $e->getMessage() .' '. $e->getLine());
        }
    }

    public function updateAvatar(Request $request)
    {
        $user = Auth::user();
        
        // 1. Validation
        $rules = [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // max 2MB
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

            return $this->successResponse($user,'Avatar updated successfully.', 200);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update avatar', 500, $e->getMessage() .' '. $e->getLine());  
        }
    }
}
