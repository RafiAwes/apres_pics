<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\{Event, User};

class EventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            ['name' => 'Laravel Global Conference', 'date' => now()->addDays(10), 'address' => 'Online'],
            ['name' => 'Startup Pitch Night', 'date' => now()->addDays(5), 'address' => 'Dhaka, Bangladesh'],
            ['name' => 'Live Coding Workshop', 'date' => now()->addDays(2), 'address' => 'New York, USA'],
        ];
        
        $users  = User::where('role', 'user')->get();

        if ($users->isEmpty()) {
            $this->command->info("No users found. Please run UserSeeder first.");
            return;
        }

        foreach ($events as $ev) {
            Event::create([
                'user_id' => $users->random()->id,
                'name' => $ev['name'],
                'date' => $ev['date'],
                'address' => $ev['address'],
                'is_active' => true
            ]);
        }
    }
}
