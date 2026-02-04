<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EventContents;
use Illuminate\Support\Facades\File;

class FixEventContentPaths extends Command
{
    protected $signature = 'event:fix-content-paths';
    protected $description = 'Fix event content image paths to store relative paths instead of full URLs';

    public function handle()
    {
        $this->info("Fixing event content image paths...");
        
        $contents = EventContents::all();
        $fixedCount = 0;
        
        foreach ($contents as $content) {
            $currentPath = $content->image;
            
            // If it's already a relative path, skip
            if (!str_starts_with($currentPath, 'http')) {
                continue;
            }
            
            // Extract the relative path from the URL
            $parsedUrl = parse_url($currentPath);
            $relativePath = ltrim($parsedUrl['path'], '/');
            
            // Check if the file actually exists at the expected location
            $expectedPath = public_path($relativePath);
            if (File::exists($expectedPath)) {
                // Update the database record directly to avoid accessor
                $content->update(['image' => $relativePath]);
                $fixedCount++;
                $this->line("✓ Fixed: {$relativePath}");
            } else {
                $this->error("File not found: {$expectedPath}");
            }
        }
        
        $this->info("✅ Fixed {$fixedCount} event content paths");
        return 0;
    }
}