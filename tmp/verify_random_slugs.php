<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use Illuminate\Support\Str;

echo "--- Verifying Existing Random Slugs ---\n";
$events = Event::all();
foreach ($events as $event) {
    echo "ID: {$event->id}, Name: {$event->name}, Slug: {$event->slug}\n";
    if (empty($event->slug)) {
        echo "ERROR: Slug is empty for ID {$event->id}\n";
    }
}

echo "\n--- Verifying New Event Random Slug Generation ---\n";
$newEvent = new Event();
$newEvent->user_id = 1;
$newEvent->name = "Test Random Event " . rand(100, 999);
$newEvent->date = now();
$newEvent->address = "Test Address";
$newEvent->save();

echo "ID: {$newEvent->id}, Name: {$newEvent->name}, Slug: {$newEvent->slug}\n";
if (!empty($newEvent->slug) && strlen($newEvent->slug) === 10) {
    echo "SUCCESS: Random slug (length 10) correctly generated for new event.\n";
} else {
    echo "ERROR: Random slug generation failed for new event.\n";
}

echo "\n--- Verifying Slug Stability on Update ---\n";
$oldSlug = $newEvent->slug;
$newEvent->name = $newEvent->name . " Updated";
$newEvent->save();

echo "ID: {$newEvent->id}, New Name: {$newEvent->name}, Slug: {$newEvent->slug}\n";
if ($newEvent->slug === $oldSlug) {
    echo "SUCCESS: Slug remained stable on name change.\n";
} else {
    echo "ERROR: Slug changed unexpectedly on update.\n";
}

// Cleanup
$newEvent->delete();
echo "\n--- Verification Complete ---\n";
