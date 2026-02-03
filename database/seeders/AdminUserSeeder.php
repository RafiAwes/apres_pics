<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $adminExists = User::where('role', 'admin')->exists();

        if (!$adminExists) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@aprespics.com',
                'password' => Hash::make('Admin@123'),
                'role' => 'admin',
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: admin@aprespics.com');
            $this->command->info('Password: Admin@123');
        } else {
            $this->command->info('Admin user already exists.');
        }
    }
}
