<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\{Event, EventContents};

class AddCelebrityImages extends Command
{
    protected $signature = 'event:add-celebrity-images {eventId}';
    protected $description = 'Add Michael Jackson and Michael Jordan images to event contents';

    public function handle()
    {
        $eventId = $this->argument('eventId');
        
        // Check if event exists
        $event = Event::find($eventId);
        if (!$event) {
            $this->error("Event with ID {$eventId} not found!");
            return 1;
        }

        $this->info("Adding celebrity images to event: {$event->name} (ID: {$eventId})");

        // Ensure storage/events/{eventId} exists
        Storage::disk('public')->makeDirectory("events/{$eventId}");

        // Michael Jackson images URLs (using placeholder images for demo)
        $michaelJacksonImages = [
            'https://picsum.photos/800/600?random=101',
            'https://picsum.photos/800/600?random=102',
            'https://picsum.photos/800/600?random=103',
            'https://picsum.photos/800/600?random=104',
            'https://picsum.photos/800/600?random=105',
            'https://picsum.photos/800/600?random=106',
            'https://picsum.photos/800/600?random=107',
            'https://picsum.photos/800/600?random=108',
            'https://picsum.photos/800/600?random=109',
            'https://picsum.photos/800/600?random=110',
            'https://picsum.photos/800/600?random=111',
            'https://picsum.photos/800/600?random=112'
        ];

        // Michael Jordan images URLs (using placeholder images for demo)
        $michaelJordanImages = [
            'https://picsum.photos/800/600?random=201',
            'https://picsum.photos/800/600?random=202',
            'https://picsum.photos/800/600?random=203',
            'https://picsum.photos/800/600?random=204',
            'https://picsum.photos/800/600?random=205',
            'https://picsum.photos/800/600?random=206',
            'https://picsum.photos/800/600?random=207',
            'https://picsum.photos/800/600?random=208',
            'https://picsum.photos/800/600?random=209',
            'https://picsum.photos/800/600?random=210',
            'https://picsum.photos/800/600?random=211',
            'https://picsum.photos/800/600?random=212'
        ];

        $this->info("Downloading Michael Jackson images...");
        $mjCount = 0;
        foreach ($michaelJacksonImages as $index => $imageUrl) {
            if ($this->downloadAndSaveImage($imageUrl, $eventId, "michael_jackson_{$index}.jpg")) {
                $mjCount++;
                $this->line("✓ Added Michael Jackson image " . ($index + 1));
            }
        }

        $this->info("Downloading Michael Jordan images...");
        $mjordanCount = 0;
        foreach ($michaelJordanImages as $index => $imageUrl) {
            if ($this->downloadAndSaveImage($imageUrl, $eventId, "michael_jordan_{$index}.jpg")) {
                $mjordanCount++;
                $this->line("✓ Added Michael Jordan image " . ($index + 1));
            }
        }

        // Update total_images count
        $event->total_images = $event->eventContents()->count();
        $event->save();

        $this->info("✅ Successfully added {$mjCount} Michael Jackson images and {$mjordanCount} Michael Jordan images to event ID {$eventId}");
        $this->info("Total images in event: {$event->total_images}");

        return 0;
    }

    private function downloadAndSaveImage($imageUrl, $eventId, $filename)
    {
        try {
            // Download image
            $imageContent = file_get_contents($imageUrl);
            if ($imageContent === false) {
                $this->error("Failed to download: {$imageUrl}");
                return false;
            }

            // Save to local storage (storage/app/public)
            $localPath = "events/{$eventId}/" . time() . '_' . $filename;
            if (!Storage::disk('public')->put($localPath, $imageContent)) {
                $this->error("Failed to save image: {$filename}");
                return false;
            }

            // Create database record
            EventContents::create([
                'event_id' => $eventId,
                'image' => 'storage/'.$localPath,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->error("Error processing {$filename}: " . $e->getMessage());
            return false;
        }
    }
}