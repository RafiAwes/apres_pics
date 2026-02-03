<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PackageSeeders extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Package::create([
            'name' => 'Pro Monthly Subscription',
            'type' => 'monthly',
            'price' => 50.00,
            'duration_days' => 30,
            // 'stripe_price_id' => 'price_test_monthly' // Uncomment if using real Stripe
        ]);

        Package::create([
            'name' => 'Single Event Pass',
            'type' => 'per_event',
            'price' => 15.00, // Base price
            'duration_days' => null,
        ]);
    }
}
