<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;
use Illuminate\Http\Request;

echo "--- Verifying Existing Alphabet-Prefixed Slugs ---\n";
$events = Event::all();
$allStartWithLetter = true;
foreach ($events as $event) {
    echo "ID: {$event->id}, Slug: {$event->slug} ";
    if (empty($event->slug)) {
        echo "- ERROR: Slug is empty\n";
        $allStartWithLetter = false;
    } elseif (!ctype_alpha($event->slug[0])) {
        echo "- ERROR: Does not start with a letter\n";
        $allStartWithLetter = false;
    } else {
        echo "- OK\n";
    }
}
if ($allStartWithLetter) echo "SUCCESS: All existing slugs start with a letter.\n";

echo "\n--- Verifying New Event Slug Generation ---\n";
$newEvent = new Event();
$newEvent->user_id = 1;
$newEvent->name = "Test Alpha Event " . rand(100, 999);
$newEvent->date = now();
$newEvent->address = "Test Address";
$newEvent->save();

echo "ID: {$newEvent->id}, Name: {$newEvent->name}, Slug: {$newEvent->slug}\n";
if (!empty($newEvent->slug) && strlen($newEvent->slug) === 10 && ctype_alpha($newEvent->slug[0])) {
    echo "SUCCESS: New slug starts with a letter.\n";
} else {
    echo "ERROR: New slug generation failed to meet criteria.\n";
}

echo "\n--- Verifying Event Contents Fetch via Slug (Controller Simulation) ---\n";
// Add a dummy content to the new event
\App\Models\EventContents::create([
    'event_id' => $newEvent->id,
    'image' => 'dummy/path/image.jpg'
]);

// Simulate the controller action
$controller = app(\App\Http\Controllers\Api\EventController::class);

// Since route implicitly binds, we pass the event model directly as if the router resolved it
$response = $controller->eventContents($newEvent);
$data = $response->getData();

if ($response->getStatusCode() === 200 && count($data->data->contents_list) > 0) {
    echo "SUCCESS: Fetched event contents successfully. Content link: {$data->data->contents_list[0]->image}\n";
} else {
    echo "ERROR: Failed to fetch event contents.\n";
}

// Cleanup
\App\Models\EventContents::where('event_id', $newEvent->id)->delete();
$newEvent->delete();

echo "\n--- Verification Complete ---\n";
