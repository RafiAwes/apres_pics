<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FaceNetService
{
    /**
     * Run the Python Script
     */
    public function run($command, $eventId, $imagePath)
    {
        $scriptPath = base_path('scripts/facenet.py');
        $dbPath = storage_path("app/public/events/{$eventId}/facenet_db.json");
        
        // Ensure database folder exists
        if (!file_exists(dirname($dbPath))) {
            mkdir(dirname($dbPath), 0755, true);
        }

        // --- WINDOWS COMPATIBLE COMMAND ---
        // 1. Use 'python'
        // 2. Wrap paths in quotes "..."
        // 3. Catch errors with 2>&1
        $cmd = "python \"{$scriptPath}\" {$command} \"{$dbPath}\" \"{$imagePath}\" 2>&1";
        
        $output = shell_exec($cmd);
        $decoded = json_decode($output, true);

        if (!$decoded) {
            $decoded = $this->extractJsonFromOutput($output);
        }

        if (!$decoded) {
            Log::error("FaceNet Service Error: " . $output);
            return ['error' => 'AI Processing Failed. Check logs.'];
        }

        return $decoded;
    }

    /**
     * Extract a JSON object from mixed stdout/stderr output.
     */
    protected function extractJsonFromOutput($output)
    {
        $lines = preg_split("/\r\n|\n|\r/", trim((string) $output));
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }
            $candidate = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }

        if (preg_match('/\{.*\}/s', (string) $output, $match)) {
            $candidate = json_decode($match[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }

        return null;
    }
}