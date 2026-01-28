<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponseTraits; // Use your trait for consistent errors!

class CheckSubscription
{
    use ApiResponseTraits;

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        // 1. Check for global monthly subscription
        if ($user->hasActiveSubscription()) {
            return $next($request);
        }

        // 2. If accessing a specific event, check event access
        // Assumption: The event_id is passed in the route, e.g., /api/events/{id}/watch
        $eventId = $request->route('id'); 
        
        if ($eventId && $user->hasEventAccess($eventId)) {
            return $next($request);
        }

        return $this->errorResponse('Subscription or Event Pass required.', 403);
    }
}