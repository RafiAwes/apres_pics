<?php

namespace App\Services;

use Illuminate\Support\Facades\{Http, Log};
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;

class GoogleVisionService
{
    protected $clientOptions;

    public function __construct()
    {
        // Prefer explicit credentials if provided, otherwise fallback to ADC
        $credentialsPath = config('services.google_vision.credentials');
        $projectId = config('services.google_vision.project_id');

        $options = [];

        if (!empty($credentialsPath)) {
            $resolvedPath = $credentialsPath;
            if (!str_starts_with($credentialsPath, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:\\\\/', $credentialsPath)) {
                $resolvedPath = base_path($credentialsPath);
            }

            $options['credentials'] = $resolvedPath;
        }

        if (!empty($projectId)) {
            $options['projectId'] = $projectId;
        }

        $this->clientOptions = $options;
    }

    /**
     * Send image to Google and get Face Landmarks
     */
    public function getFaceLandmarks($imagePath)
    {
        try {
            $client = new ImageAnnotatorClient($this->clientOptions ?? []);
            $imageContent = null;

            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                $response = Http::get($imagePath);
                if ($response->ok()) {
                    $imageContent = $response->body();
                }
            } else {
                if (!is_string($imagePath) || !file_exists($imagePath)) {
                    Log::warning('Image path not found for Vision processing.', [
                        'image_path' => $imagePath,
                    ]);
                } else {
                    $imageContent = file_get_contents($imagePath);
                }
            }

            if (!$imageContent) {
                Log::warning('Image content could not be loaded for Vision processing.', [
                    'image_path' => $imagePath,
                ]);
                return null;
            }
            
            // Ask Google to DETECT_FACES
            $response = $client->faceDetection($imageContent);
            $faces = $response->getFaceAnnotations();

            if (count($faces) === 0) {
                return null;
            }

            // We take the first/largest face found
            $face = $faces[0];

            // Extract the 3D coordinates of key landmarks (Eyes, Nose, Mouth, etc.)
            $landmarks = [];
            foreach ($face->getLandmarks() as $landmark) {
                $landmarks[] = [
                    'type' => $landmark->getType(),
                    'x' => $landmark->getPosition()->getX(),
                    'y' => $landmark->getPosition()->getY(),
                    'z' => $landmark->getPosition()->getZ(),
                ];
            }

            return $landmarks;

        } catch (\Exception $e) {
            Log::error("Google Vision Error: " . $e->getMessage(), [
                'image_path' => $imagePath,
            ]);
            return null;
        } finally {
            if (isset($client)) {
                $client->close();
            }
        }
    }

    /**
     * Compare two sets of landmarks (Euclidean Distance)
     * Lower score = Better match
     */
    public function compareFaces($landmarks1, $landmarks2)
    {
        if (!$landmarks1 || !$landmarks2) return 999; // No match

        $score = 0;
        $count = 0;

        // Compare position of each landmark (e.g., Nose Tip, Left Eye)
        foreach ($landmarks1 as $index => $point1) {
            if (isset($landmarks2[$index])) {
                $point2 = $landmarks2[$index];

                // Calculate Euclidean distance between the points
                $diffX = pow($point1['x'] - $point2['x'], 2);
                $diffY = pow($point1['y'] - $point2['y'], 2);
                $diffZ = pow($point1['z'] - $point2['z'], 2);
                
                $score += sqrt($diffX + $diffY + $diffZ);
                $count++;
            }
        }

        // Return average distance
        return ($count > 0) ? $score / $count : 999;
    }
}