<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log, Storage};
use App\Http\Controllers\Controller;
use App\Jobs\IndexFaceJob;
use App\Models\{Event, EventContents};
use App\Services\{EventService, FaceNetService};
use App\Traits\{ApiResponseTraits, ImageTrait};

class EventController extends Controller
{

    use ApiResponseTraits, ImageTrait;

    protected $faceNet;
    protected $eventService;
    public function __construct(FaceNetService $faceNet, EventService $eventService)
    {
        $this->eventService = $eventService;
        $this->faceNet = $faceNet;
    }

    public function events(Request $request)
    {
        $search = $request->search ?? '';
        $per_page = $request->per_page ?? 10;
        $user = Auth::user();
        if( $user->role == 'user'){
            $events = Event::where('user_id', $user->id)->where('name', 'LIKE', '%'.$search.'%')->with('user')->paginate($per_page);
        }elseif($user->role == 'admin'){
            $events = Event::where('name', 'LIKE', '%'.$search.'%')->with('user')->paginate($per_page);
        }
        
        else{
            $events = Event::where('name', 'LIKE', '%'.$search.'%')->with('user')->paginate($per_page);
        }

        return $this->successResponse($events, 'Events fetched successfully', 200);
    }

    public function createEvent(Request $request)
    {
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'address' => 'required|string|max:500',
        ]);

        try {
            $event = Event::create([
                'user_id' => Auth::id(),
                'name' => $request->name,
                'date' => $request->date,
                'address' => $request->address,
                'is_active' => true,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse($event, 'Event created successfully', 200);
    }

    public function updateEvent(Request $request, $id)
    {

        $request->validate([
            'name' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'address' => 'nullable|string|max:500',
        ]);

        try {

            $event = Event::findByIdOrSlug($id);
            $user = Auth::user();

            if (! $event || ($user->role == 'user' && $event->user_id !== Auth::id())) {
                return $this->errorResponse('Event not found or you are not authorized to edit it.', 404);
            }

            $updateData = [];
            
            if ($request->filled('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->filled('date')) {
                $updateData['date'] = $request->date;
            }

            if ($request->filled('address')) {
                $updateData['address'] = $request->address;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }

            $event->update($updateData);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse($event, 'Event updated successfully', 200);
    }

    public function deleteEvent($id)
    {
        try {
            $event = Event::findByIdOrSlug($id);
            $user = Auth::user();

            if (! $event || ($user->role == 'user' && $event->user_id !== Auth::id())) {
                return $this->errorResponse('Event not found or you are not authorized to delete it.', 404);
            }
            // Remove FaceNet DB for this event (if present)
            $faceDb = storage_path("app/public/events/{$event->id}/facenet_db.json");
            if (file_exists($faceDb)) {
                try { unlink($faceDb); } catch (\Throwable $e) { Log::error('Failed to delete facenet DB: '.$e->getMessage()); }
            }

            // Remove public storage folder for the event (images + facenet DB)
            try {
                Storage::disk('public')->deleteDirectory("events/{$event->id}");
            } catch (\Throwable $e) {
                Log::error('Failed to delete public event directory: '.$e->getMessage());
            }

            $event->delete();
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse(null, 'Event deleted successfully', 200);
    }

    public function deleteMultipleEvents(Request $request)
    {
        // 1. Validation
        $request->validate([
            'event_ids' => 'required|array',
            'event_ids.*' => 'exists:events,id',
        ]);

        try {
            $user = Auth::user();
            // 2. Fetching (FIXED: Used whereIn instead of where)
            $events = Event::whereIn('id', $request->event_ids)
                ->when($user->role == 'user', function($query) {
                    return $query->where('user_id', Auth::id());
                })
                ->get();

            // 3. Check if empty (FIXED: Collections are never strictly "false")
            if ($events->isEmpty()) {
                return $this->errorResponse('Events not found or you are not authorized to delete them.', 404);
            }

            // 4. Execution
            $events->each->delete();
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse(null, 'Events deleted successfully', 200);
    }

    public function UploadContent(Request $request)
    {
        $request->validate([
            'event_id' => 'required', // Could be ID or Slug
            'images' => 'required|array',
            'images.*' => 'file|mimetypes:image/*|max:12288',
        ]);

        try {

            $event = Event::findByIdOrSlug($request->event_id);
            $user = Auth::user();

            if (! $event || ($user->role == 'user' && $event->user_id !== Auth::id())) {
                return $this->errorResponse('Event not found or you are not authorized to upload content to it.', 404);
            }

            // 1. Initialize the array to hold the new records
            $uploadedContents = [];

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {

                    // Upload Image
                    $imagePath = $this->handleImageUpload($imageFile, $event->id);

                    // Create DB Record
                    $content = EventContents::create([
                        'event_id' => $event->id,
                        'image' => $imagePath,
                    ]);

                    // Update total images count
                    $this->updateTotalImages($event->id);

                    // Add to the response list (so the user sees it immediately)
                    $uploadedContents[] = $content;

                    // Dispatch AI Job (Background)
                    $fileName = basename($imagePath);
                    $absPath = storage_path("app/public/events/{$event->id}/{$fileName}");
                    IndexFaceJob::dispatch($event->id, $absPath);
                }
            }
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        // 2. Return the populated array here!
        return $this->successResponse($uploadedContents, 'Images uploaded. AI is processing in the background.', 200);
    }

    public function deleteContent($id)
    {
        try {
            $content = EventContents::whereKey($id)->first();

            if (! $content) {
                return $this->errorResponse('Content not found.', 404);
            }

            $user = Auth::user();
            $event = Event::where('id', $content->event_id)
                ->when($user->role == 'user', function($query) {
                    return $query->where('user_id', Auth::id());
                })
                ->first();

            if (! $event) {
                return $this->errorResponse('You are not authorized to delete this content.', 403);
            }

            // Try to notify FaceNet to remove indexed embeddings for this image.
            $absPath = $this->resolveAbsoluteImagePath($content->image);
            if ($absPath) {
                try {
                    $this->faceNet->run('delete', $content->event_id, $absPath);
                } catch (\Throwable $e) {
                    Log::error('FaceNet delete failed: '.$e->getMessage());
                }
            }

            // Delete the image file
            $this->deleteImage($content->image);

            $content->delete();
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse(null, 'Content deleted successfully', 200);
    }

    public function eventContents(Event $event)
    {
        try {
            // 1. Check if event exists (Implicit binding usually handles this, but good for safety)
            if (! $event->exists) {
                return $this->errorResponse('Event not found.', 404);
            }

            $user = Auth::user();
            // 2. Check authorization (users can only view their own event contents, admins can view all)
            if ($user->role == 'user' && $event->user_id !== Auth::id()) {
                return $this->errorResponse('You are not authorized to view this event\'s contents.', 403);
            }

            // 3. Fetch the contents manually
            $contents = EventContents::where('event_id', $event->id)->get();

            // 4. Structure the data: Event Details first, then Contents
            $data = [
                'event_details' => $event, // This contains id, name, date, etc.
                'contents_list' => $contents,
            ];
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        // 4. Return the combined data
        return $this->successResponse($data, 'Event details and contents fetched successfully', 200);
    }

    public function generateEventPassword()
    {
        $password = $this->eventService->generatePassword();
        return $this->successResponse(['password' => $password], 'Event password generated successfully', 200);
    }

    public function setEventPassword(Request $request, $eventId)
    {
        $request->validate([
            'password' => 'required|string|min:6|max:6',
        ]);

        try {
            $event = Event::findByIdOrSlug($eventId);
            $user = Auth::user();

            if (! $event || ($user->role == 'user' && $event->user_id !== Auth::id())) {
                return $this->errorResponse('Event not found or you are not authorized to edit it.', 404);
            }

            $this->eventService->setPasswordForEvent($event, $request->password);
            $event->save();
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse($event, 'Event password set successfully', 200);
    }

    public function eventDetails($id)
    {
        try {
            $event = Event::findByIdOrSlug($id);
            $user = Auth::user();

            if (!$event || ($user->role == 'user' && $event->user_id !== Auth::id())) {
                return $this->errorResponse('Event not found or you are not authorized to view it.', 404);
            }


            return $this->successResponse($event, 'Event details fetched successfully', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function editContent(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|file|mimetypes:image/*|max:12288',
        ]);

        try {
            $content = EventContents::findOrFail($id);
            $user = Auth::user();
            $event = Event::where('id', $content->event_id)
                ->when($user->role == 'user', function($query) {
                    return $query->where('user_id', Auth::id());
                })
                ->first();

            if (!$event) {
                return $this->errorResponse('You are not authorized to edit this content.', 403);
            }

            if ($content->getRawOriginal('image')) {
                $oldImage = $content->getRawOriginal('image');

                // Notify FaceNet to remove indexed embeddings for the old image
                $oldAbs = $this->resolveAbsoluteImagePath($oldImage);
                if ($oldAbs) {
                    try {
                        $this->faceNet->run('delete', $content->event_id, $oldAbs);
                    } catch (\Throwable $e) {
                        Log::error('FaceNet delete failed during editContent: '.$e->getMessage());
                    }
                }

                $this->deleteImage($oldImage);
            }
            $imagePath = $this->uploadImage($request, 'image', "events/{$event->id}");
            $content->update(['image' => $imagePath]);

            // Dispatch AI Job (Background)
            $fileName = basename($imagePath);
            $absPath = storage_path("app/public/events/{$event->id}/{$fileName}");
            IndexFaceJob::dispatch($event->id, $absPath);

            return $this->successResponse($content, 'Content updated successfully. AI is processing in the background.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function handleImageUpload($imageFile, $eventId)
    {
        $tempRequest = request();
        $tempRequest->files->set('image', $imageFile);
        return $this->uploadImage($tempRequest, 'image', "events/{$eventId}");
    }

    private function updateTotalImages($eventId)
    {
        $event = Event::findOrFail($eventId);
        if ($event) {
            $event->total_images = EventContents::where('event_id', $eventId)->count();
            $event->save();
        }
    }

    // Helper: resolve an absolute filesystem path for stored image references (storage disk or public URL)
    private function resolveAbsoluteImagePath($imagePath)
    {
        if (! $imagePath) return null;

        $relativePath = $imagePath;

        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($imagePath, PHP_URL_PATH);
            $relativePath = ltrim((string) $parsed, '/');
        } else {
            $relativePath = ltrim($imagePath, '/');
        }

        if (str_starts_with($relativePath, 'storage/')) {
            $storageRelativePath = substr($relativePath, strlen('storage/'));
            if (Storage::disk('public')->exists($storageRelativePath)) {
                return Storage::disk('public')->path($storageRelativePath);
            }
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->path($relativePath);
        }

        $fullPath = public_path($relativePath);
        return \Illuminate\Support\Facades\File::exists($fullPath) ? $fullPath : null;
    }

    public function eventGuestList($eventId)
    {
        try {
            $event = Event::findByIdOrSlug($eventId);
            $user = Auth::user();

            if (!$event || ($user->role == 'user' && $event->user_id !== Auth::id())) {
                return $this->errorResponse('Event not found or you are not authorized to view its guests.', 404);
            }

            $guests = \App\Models\Guest::where('event_id', $event->id)
                ->select('id', 'email')
                ->get();

            return $this->successResponse($guests, 'Guest list fetched successfully.', 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
