<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\EventContents;
use App\Services\FaceNetService;
use Illuminate\Support\Facades\Storage;

class FaceNetTestSearch extends Command
{
    protected $signature = 'facenet:test-search {eventId} {--selfie=public/images/events/test.jpg} {--reset}';
    protected $description = 'Index event images and run FaceNet search with a selfie image';

    public function handle(FaceNetService $faceNet)
    {
        $eventId = (int) $this->argument('eventId');
        $selfieInput = $this->option('selfie');

        $event = Event::find($eventId);
        if (!$event) {
            $this->error("Event with ID {$eventId} not found.");
            return 1;
        }

        $contents = EventContents::where('event_id', $eventId)->get();
        if ($contents->isEmpty()) {
            $this->warn("No event contents found for event ID {$eventId}.");
        }

        if ($this->option('reset')) {
            $dbPath = storage_path("app/public/events/{$eventId}/facenet_db.json");
            if (file_exists($dbPath)) {
                @unlink($dbPath);
            }
        }

        $this->info("Indexing images for event ID {$eventId}...");
        foreach ($contents as $content) {
            $imagePath = $content->image;
            $absolutePath = $this->resolveAbsolutePath($imagePath);

            if (!$absolutePath || !file_exists($absolutePath)) {
                $this->warn("Skipping missing image: {$imagePath}");
                continue;
            }

            $this->ensureStoredForEvent($absolutePath, $eventId);

            $result = $faceNet->run('index', $eventId, $absolutePath);
            if (isset($result['error'])) {
                $this->warn("Index error for {$imagePath}: {$result['error']}");
            }
        }

        $selfiePath = $this->resolveSelfiePath($selfieInput);
        if (!$selfiePath || !file_exists($selfiePath)) {
            $this->error("Selfie not found: {$selfieInput}");
            return 1;
        }

        $this->info("Running search with selfie: {$selfiePath}");
        $searchResult = $faceNet->run('search', $eventId, $selfiePath);

        $this->line(json_encode($searchResult, JSON_PRETTY_PRINT));

        return 0;
    }

    private function resolveAbsolutePath(string $imagePath): ?string
    {
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            $parsed = parse_url($imagePath);
            if (isset($parsed['path'])) {
                $imagePath = ltrim($parsed['path'], '/');
            }
        }

        if (str_starts_with($imagePath, 'storage/')) {
            $relative = substr($imagePath, strlen('storage/'));
            return storage_path('app/public/'.$relative);
        }

        return public_path($imagePath);
    }

    private function resolveSelfiePath(string $selfieInput): ?string
    {
        if (str_starts_with($selfieInput, 'storage/')) {
            $relative = substr($selfieInput, strlen('storage/'));
            return storage_path('app/public/'.$relative);
        }

        if (str_starts_with($selfieInput, 'public/')) {
            return public_path(substr($selfieInput, strlen('public/')));
        }

        if (file_exists($selfieInput)) {
            return $selfieInput;
        }

        return public_path($selfieInput);
    }

    private function ensureStoredForEvent(string $absolutePath, int $eventId): void
    {
        $filename = basename($absolutePath);
        $targetPath = "events/{$eventId}/{$filename}";

        if (Storage::disk('public')->exists($targetPath)) {
            return;
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            $this->warn("Failed to read image for copy: {$absolutePath}");
            return;
        }

        Storage::disk('public')->put($targetPath, $contents);
    }
}
