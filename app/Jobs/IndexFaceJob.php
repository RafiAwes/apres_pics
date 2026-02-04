<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use App\Services\FaceNetService;
use Illuminate\Support\Facades\Storage;

class IndexFaceJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    protected $eventId;
    protected $imagePath;

    /**
     * Create a new job instance.
     */
    public function __construct($eventId, $imagePath)
    {
        $this->eventId = $eventId;
        $this->imagePath = $imagePath;
    }

    /**
     * Execute the job.
     */
    public function handle(FaceNetService $faceNetService): void
    {
        $this->ensureStoredForEvent($this->imagePath);
        $faceNetService->run('index', $this->eventId, $this->imagePath);    
    }

    protected function ensureStoredForEvent($absolutePath): void
    {
        if (!is_string($absolutePath) || !file_exists($absolutePath)) {
            return;
        }

        $filename = basename($absolutePath);
        $targetPath = "events/{$this->eventId}/{$filename}";

        if (Storage::disk('public')->exists($targetPath)) {
            return;
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            return;
        }

        Storage::disk('public')->put($targetPath, $contents);
    }
}
