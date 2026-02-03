<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\{Event, Subscription, User};
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTraits;

class AdminController extends Controller
{
    use ApiResponseTraits;
    public function dashboardStats()
    {
        // Example statistics - replace with actual logic
        $totalUsers = User::count();
        $totalEvents = Event::count();
        $totalSubscriptions = Subscription::count();

        $stats = [
            'total_users' => $totalUsers,
            'total_events' => $totalEvents,
            'total_subscriptions' => $totalSubscriptions,
        ];

            return $this->successResponse($stats, 'Dashboard statistics fetched successfully', 200);
    }
}
