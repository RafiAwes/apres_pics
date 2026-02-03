<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GoogleVisionService;
use App\Models\EventContents;
use App\Traits\ApiResponseTraits;

class VisionController extends Controller
{
    use ApiResponseTraits;

    protected $vision;

    public function __construct(GoogleVisionService $vision)
    {
        $this->vision = $vision;
    }

    public function searchByFace(Request $request)
    {
        $request->validate([
            'selfie' => 'required|image|max:5120', // Max 5MB per image
            'event_id' => 'required|exists:events,id',
        ]);

        $selfiePath = $request->file('selfie')->getRealPath();
        $selfieLandmarks = $this->vision->getFaceLandmarks($selfiePath);

        if (!$selfieLandmarks) {
            return $this->errorResponse('No face detected in the selfie image.', 400);
        }

        $photos  = EventContents::where('event_id', $request->event_id)->get();

        $mathches = [];

        foreach ($photos as $photo) {
            if ($photo->landmarks_json) {
                $photoLandmarks = json_decode($photo->landmarks_json, true);
            }

            else{
                $photoLandmarks = $this->vision->getFaceLandmarks($photo->file_path);
                if ($photoLandmarks) {
                    $photo->landmarks_json = json_encode($photoLandmarks);
                    $photo->save();
                }
            }

            $similarityScore = $this->vision->compareFaces($selfieLandmarks, $photoLandmarks);

            if ($similarityScore < 50) {
                $matches[] = [
                    'url' => $photo->url,
                    'score' => $similarityScore,
                ];
            }
        }

        usort($matches, function($a, $b) {
            return $a['score'] <=> $b['score'];
        });

        return $this->successResponse($matches, 'Matching photos found.', 200);
    }
}
