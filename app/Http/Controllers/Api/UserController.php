<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function UserList(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $search = $request->query('search', '');

        $query = User::where('role', 'user')->where('ban_type',null)
            ->select('id', 'name', 'email', 'avatar');

        if ($search) {
            $query->where('name', 'LIKE', '%'.$search.'%')
                ->orWhere('email', 'LIKE', '%'.$search.'%');
        }

        $usersPaginator = $query->paginate($perPage);

        $users = $usersPaginator->through(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'User list fetched successfully.',
            'data' => $users,
        ], 200);

    }

     public function banUser(Request $request, User $user)
    {
        $data = $request->validate([
            'type' => 'required|in:permanently,week,month,year,unban',
            'reason' => 'nullable|string|max:255',
        ]);

        $banType = $data['type'];
        $bannedUntil = null;

        switch ($banType) {
            case 'permanently':
                $bannedUntil = null;
                break;
            case 'week':
                $bannedUntil = Carbon::now()->addWeek();
                break;
            case 'month':
                $bannedUntil = Carbon::now()->addMonth();
                break;
            case 'year':
                $bannedUntil = Carbon::now()->addYear();
                break;
            case 'unban':
                $banType = null;
                $bannedUntil = null;
                break;
        }

        $user->update([
            'ban_type' => $banType,
            'ban_expires_at' => $bannedUntil,
            'ban_reason' => $data['reason'] ?? null,
        ]);

        $message = $banType === null
            ? 'User has been unbanned successfully.'
            : 'User has been banned ('.$data['type'].').';

        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'ban_type' => $user->ban_type,
                'ban_expires_at' => $user->ban_expires_at,
                'ban_reason' => $user->ban_reason,
            ],
        ]);
    }
}
