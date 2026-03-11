<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\{Event, Subscription, Transaction, User};
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

    public function transactionList(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $search  = $request->query('search', '');

        $transactions = Transaction::with(['user', 'package'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', function ($u) use ($search) {
                    $u->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhere('email', 'LIKE', '%' . $search . '%');
                });
            })
            ->latest()
            ->paginate($perPage);

        $data = $transactions->through(function ($transaction) {
            $createdAt = $transaction->getRawOriginal('created_at')
                ? \Carbon\Carbon::parse($transaction->getRawOriginal('created_at'))
                : null;

            return [
                'id'               => $transaction->id,
                'stripe_payment_id' => $transaction->stripe_payment_id,
                'amount'           => (float) $transaction->amount,
                'currency'         => strtoupper($transaction->currency),
                'type'             => $transaction->type,
                'status'           => $transaction->status,
                'meta_data'        => $transaction->meta_data,
                'date'             => $createdAt ? $createdAt->format('jS F, Y') : null,
                'time'             => $createdAt ? $createdAt->format('h:i a') : null,
                'user' => [
                    'id'     => optional($transaction->user)->id,
                    'name'   => optional($transaction->user)->name,
                    'email'  => optional($transaction->user)->email,
                    'avatar' => optional($transaction->user)->avatar,
                ],
                'package' => [
                    'id'            => optional($transaction->package)->id,
                    'name'          => optional($transaction->package)->name,
                    'type'          => optional($transaction->package)->type,
                    'price'         => optional($transaction->package)->price,
                    'duration_days' => optional($transaction->package)->duration_days,
                ],
            ];
        });

        return $this->successResponse($data, 'Transaction list fetched successfully', 200);
    }

    public function subscriptionList(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $search  = $request->query('search', '');

        $query = Subscription::with(['user', 'package'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('user', function ($u) use ($search) {
                    $u->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhere('email', 'LIKE', '%' . $search . '%');
                });
            })
            ->latest();

        $paginator = $query->paginate($perPage);

        $data = $paginator->through(function ($subscription) {
            $purchasedAt = $subscription->getRawOriginal('created_at')
                ? \Carbon\Carbon::parse($subscription->getRawOriginal('created_at'))
                : null;

            return [
                'id'           => $subscription->id,
                'user_name'    => optional($subscription->user)->name,
                'email'        => optional($subscription->user)->email,
                'avatar'       => optional($subscription->user)->avatar,
                'package'      => optional($subscription->package)->name,
                'package_type' => optional($subscription->package)->type,
                'purchase_date' => $purchasedAt ? $purchasedAt->format('jS F, Y') : null,
                'purchase_time' => $purchasedAt ? $purchasedAt->format('h:i a') : null,
            ];
        });

        return $this->successResponse($data, 'Subscription list fetched successfully', 200);
    }

    public function totalRevenue()
    {
        $totalRevenue = (float) Transaction::where('status', 'paid')->sum('amount');

        return $this->successResponse([
            'total_revenue' => round($totalRevenue, 2),
        ], 'Total revenue fetched successfully', 200);
    }

    public function currentWeeklyRevenue()
    {
        $weeklyStart = now()->startOfWeek()->startOfDay();
        $weeklyEnd = now()->endOfDay();

        $weeklyTransactions = Transaction::where('status', 'paid')
            ->whereBetween('created_at', [$weeklyStart, $weeklyEnd])
            ->get(['amount', 'created_at']);

        $weeklyTotals = $weeklyTransactions
            ->groupBy(function ($transaction) {
                return $transaction->created_at->toDateString();
            })
            ->map(function ($items) {
                return (float) $items->sum('amount');
            });

        $labels = [];
        $values = [];
        $date = $weeklyStart->copy();
        while ($date->lte($weeklyEnd)) {
            $key = $date->toDateString();
            $labels[] = $date->format('D');
            $values[] = round((float) ($weeklyTotals[$key] ?? 0), 2);
            $date->addDay();
        }

        return $this->successResponse([
            'labels' => $labels,
            'values' => $values,
            'total' => round(array_sum($values), 2),
        ], 'Current weekly revenue fetched successfully', 200);
    }

    public function currentMonthlyRevenue()
    {
        $monthlyStart = now()->startOfMonth()->startOfDay();
        $monthlyEnd = now()->endOfMonth()->endOfDay();

        $monthlyTransactions = Transaction::where('status', 'paid')
            ->whereBetween('created_at', [$monthlyStart, $monthlyEnd])
            ->get(['amount', 'created_at']);

        $totalWeeks = (int) ceil($monthlyStart->daysInMonth / 7);
        $labels = [];
        $values = array_fill(0, $totalWeeks, 0.0);

        for ($week = 1; $week <= $totalWeeks; $week++) {
            $labels[] = 'Week ' . $week;
        }

        foreach ($monthlyTransactions as $transaction) {
            $dayOfMonth = (int) $transaction->created_at->day;
            $weekIndex = intdiv($dayOfMonth - 1, 7);
            $values[$weekIndex] += (float) $transaction->amount;
        }

        $values = array_map(function ($value) {
            return round((float) $value, 2);
        }, $values);

        return $this->successResponse([
            'labels' => $labels,
            'values' => $values,
            'total' => round(array_sum($values), 2),
        ], 'Current monthly revenue fetched successfully', 200);
    }
}
