<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Auth, Hash};
use App\Traits\ImageTrait;

class ProfileController extends Controller
{
    use ImageTrait;
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

                if ($request->filled('phone_number')) {
                    $user->phone_number = $request->phone_number;
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

            return response()->json([
                'status' => true,
                'message' => 'Profile info updated successfully',
                'data' => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update profile info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function UpdateAvatar(Request $request)
    {
        $user = Auth::user();
        
        // 1. Validation
        $rules = [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // max 2MB
        ];

        $request->validate($rules);

        try {
            // 2. Upload new avatar
            $imagePath = $this->uploadAvatar($request, 'avatar', 'uploads/avatars');

            // 3. Delete old avatar if exists
            if ($user->avatar) {
                $this->deleteImage($user->avatar);
            }

            // 4. Update user record
            $user->update(['avatar' => $imagePath]);

            return response()->json([
                'status' => true,
                'message' => 'Avatar updated successfully',
                'data' => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update avatar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
