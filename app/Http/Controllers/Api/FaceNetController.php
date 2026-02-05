<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FaceNetService; 
use App\Traits\ApiResponseTraits;
use Illuminate\Support\Facades\Storage;

class FaceNetController extends Controller
{
    use ApiResponseTraits;

    protected $faceNet;

    public function __construct(FaceNetService $faceNet)
    {
        $this->faceNet = $faceNet;
    }

    public function search(Request $request)
    {
        // 1. Validation
        $request->validate([
            'selfie'   => 'required|file|mimetypes:image/*|max:5120', // 5MB limit
            'event_id' => 'required|exists:events,id',
        ]);
        
        $eventId = $request->event_id;
        
        try {
            // 2. Save Selfie Temporarily
            // We need a physical file for Python to read
            $path = $request->file('selfie')->store('temp', 'public');
            $absPath = Storage::disk('public')->path($path);

            // 3. Run the AI Search
            // This might take 2-4 seconds depending on CPU
            $result = $this->faceNet->run('search', $eventId, $absPath);
            
            // 4. Cleanup (Delete the temp selfie)
            Storage::disk('public')->delete($path);

            // 5. Handle Errors
            if (isset($result['error'])) {
                // If "No face detected", we return 400 so the frontend knows to ask for a better photo
                if (str_contains($result['error'], 'No face detected')) {
                     return $this->errorResponse("Could not detect a face. Please try a clearer selfie.", 400);
                }
                return $this->errorResponse($result['error'], 500);
            }

            // 6. Format the Results
            // Python gives us filenames: ["photo1.jpg", "photo2.jpg"]
            // We convert them to full URLs: ["http://.../photo1.jpg", ...]
            $matches = collect($result['matches'] ?? [])->map(function($filename) use ($eventId) {
                // Images are stored under storage/events/{eventId}
                return asset("storage/events/{$eventId}/{$filename}");
            });

            return $this->successResponse($matches, "Found " . count($matches) . " photos of you.", 200);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}