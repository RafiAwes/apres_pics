<?php

namespace App\Services;

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Illuminate\Support\Facades\Log;

class GoogleVisionService
{
    protected $client;

    public function __construct()
    {
        // Google client automatically finds credentials from the .env variable
        $this->client = new ImageAnnotatorClient();
    }

    /**
     * Send image to Google and get Face Landmarks
     */
    public function getFaceLandmarks($imagePath)
    {
        try {
            $imageContent = file_get_contents($imagePath);
            
            // Ask Google to DETECT_FACES
            $response = $this->client->faceDetection($imageContent);
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
            Log::error("Google Vision Error: " . $e->getMessage());
            return null;
        } finally {
            $this->client->close();
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