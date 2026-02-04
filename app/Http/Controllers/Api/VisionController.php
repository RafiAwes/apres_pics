<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GoogleVisionService;
use App\Models\EventContents;
use App\Traits\ApiResponseTraits;
use Illuminate\Support\Facades\Storage;

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

        $selfieUploadPath = $request->file('selfie')->store('temp/selfies', 'public');
        $selfiePath = storage_path('app/public/'.$selfieUploadPath);
        $selfieLandmarks = $this->vision->getFaceLandmarks($selfiePath);

        if (!$selfieLandmarks) {
            return $this->errorResponse('No face detected in the selfie image.', 400);
        }

        $photos  = EventContents::where('event_id', $request->event_id)->get();

        $matches = [];

        foreach ($photos as $photo) {
            if ($photo->landmarks_json) {
                $photoLandmarks = json_decode($photo->landmarks_json, true);
            }

            else{
                $rawImagePath = $photo->getRawOriginal('image');
                $imagePath = $rawImagePath;

                if ($rawImagePath && !preg_match('#^https?://#i', $rawImagePath)) {
                    $imagePath = public_path($rawImagePath);
                }

                $photoLandmarks = $this->vision->getFaceLandmarks($imagePath ?? $photo->image);
                if ($photoLandmarks) {
                    $photo->landmarks_json = json_encode($photoLandmarks);
                    $photo->save();
                }
            }

            if (!$photoLandmarks) {
                continue;
            }

            $similarityScore = $this->vision->compareFaces($selfieLandmarks, $photoLandmarks);

            if ($similarityScore < 50) {
                $matches[] = [
                    'url' => $photo->image,
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
