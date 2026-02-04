<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventContents;
use Illuminate\Support\Facades\Storage;

class MigrateEventImagesToStorage extends Command
{
    protected $signature = 'event:migrate-images {eventId?}';
    protected $description = 'Move legacy public/images/events files into storage/events/{eventId} and update DB paths';

    public function handle()
    {
        $eventId = $this->argument('eventId');

        $query = EventContents::query();
        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $contents = $query->get();
        if ($contents->isEmpty()) {
            $this->warn('No event contents found to migrate.');
            return 0;
        }

        foreach ($contents as $content) {
            $imagePath = $content->image;
            if (str_starts_with($imagePath, 'storage/')) {
                continue;
            }

            $absolutePath = $this->resolveAbsolutePath($imagePath);
            if (!$absolutePath || !file_exists($absolutePath)) {
                $this->warn("Missing file for record {$content->id}: {$imagePath}");
                continue;
            }

            $filename = basename($absolutePath);
            $targetPath = "events/{$content->event_id}/{$filename}";

            Storage::disk('public')->makeDirectory("events/{$content->event_id}");

            $contentsBytes = file_get_contents($absolutePath);
            if ($contentsBytes === false) {
                $this->warn("Failed to read file: {$absolutePath}");
                continue;
            }

            if (!Storage::disk('public')->put($targetPath, $contentsBytes)) {
                $this->warn("Failed to store file: {$targetPath}");
                continue;
            }

            $content->image = 'storage/'.$targetPath;
            $content->save();
        }

        $this->info('Migration completed.');
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

        if (str_starts_with($imagePath, 'public/')) {
            return public_path(substr($imagePath, strlen('public/')));
        }

        return public_path($imagePath);
    }
}
