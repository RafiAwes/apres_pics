<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Event;

echo "--- Fetching Random Event for Test ---\n";
$event = Event::first();
if (!$event) {
    echo "ERROR: No events found in DB\n";
    exit;
}

$id = $event->id;
$slug = $event->slug;

echo "Event ID: $id\n";
echo "Event Slug: $slug\n";

echo "\n--- Testing lookup by ID via findByIdOrSlug ---\n";
$lookupById = Event::findByIdOrSlug($id);
if ($lookupById && $lookupById->id === $id) {
    echo "SUCCESS: Found event by ID\n";
} else {
    echo "ERROR: Could not find event by ID\n";
}

echo "\n--- Testing lookup by Slug via findByIdOrSlug ---\n";
$lookupBySlug = Event::findByIdOrSlug($slug);
if ($lookupBySlug && $lookupBySlug->id === $id) {
    echo "SUCCESS: Found event by Slug\n";
} else {
    echo "ERROR: Could not find event by Slug\n";
}

echo "\n--- Testing Route Model Binding (Simulated) ---\n";
// This tests the resolveRouteBinding method
$eventModel = new Event();
$resolvedById = $eventModel->resolveRouteBinding($id);
if ($resolvedById && $resolvedById->id === $id) {
    echo "SUCCESS: Route Binding resolved by ID\n";
} else {
    echo "ERROR: Route Binding failed for ID\n";
}

$resolvedBySlug = $eventModel->resolveRouteBinding($slug);
if ($resolvedBySlug && $resolvedBySlug->id === $id) {
    echo "SUCCESS: Route Binding resolved by Slug\n";
} else {
    echo "ERROR: Route Binding failed for Slug\n";
}

echo "\n--- Verification Complete ---\n";
