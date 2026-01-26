<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $email = "user{$i}@example.com";

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "User {$i}",
                    'password' => Hash::make('12345678'),
                    'role' => 'user',
                    'is_active' => true,
                ]
            );
        }
    }
}
