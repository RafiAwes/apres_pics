<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\IndexFaceJob;
use App\Models\{Event, EventContents};
use App\Services\{EventService, FaceNetService, GuestService};
use App\Traits\{ApiResponseTraits, ImageTrait};
use App\Http\Controllers\Controller;

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
        $events = Event::orderBy('created_at', 'desc')->paginate(10);

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
            $event = new Event;
            $event->user_id = Auth::id();
            $event->name = $request->name;
            $event->date = $request->date;
            $event->address = $request->address;
            $event->is_active = true;
            $event->save();
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

            $event = Event::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (! $event) {
                return $this->errorResponse('Event not found or you are not authorized to edit it.', 404);
            }

            if ($request->filled('name')) {
                $event->name = $request->name;
            }

            if ($request->filled('date')) {
                $event->date = $request->date;
            }

            if ($request->filled('address')) {
                $event->address = $request->address;
            }

            if ($request->has('is_active')) {
                $event->is_active = $request->is_active;
            }

            $event->save();

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse($event, 'Event updated successfully', 200);
    }

    public function deleteEvent($id)
    {
        try {
            $event = Event::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (! $event) {
                return $this->errorResponse('Event not found or you are not authorized to delete it.', 404);
            }

            $event->delete();
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse(null, 'Event deleted successfully', 200);
    }

    public function UploadContent(Request $request)
    {
        $request->validate([
            'event_id' => 'required|exists:events,id',
            'images' => 'required|array',
            'images.*' => 'file|mimetypes:image/*|max:2048',
        ]);

        try {
            $event = Event::where('id', $request->event_id)
                ->where('user_id', Auth::id())
                ->first();

            if (! $event) {
                return $this->errorResponse('Event not found or unauthorized.', 404);
            }

            // 1. Initialize the array to hold the new records
            $uploadedContents = [];

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {

                    // Upload Image
                    $tempRequest = new Request;
                    $tempRequest->files->set('image', $imageFile);
                    $imagePath = $this->uploadImage($tempRequest, 'image', "events/{$request->event_id}");

                    // Create DB Record
                    $content = EventContents::create([
                        'event_id' => $request->event_id,
                        'image' => $imagePath,
                    ]);

                    // Add to the response list (so the user sees it immediately)
                    $uploadedContents[] = $content;

                    // Dispatch AI Job (Background)
                    $fileName = basename($imagePath);
                    $absPath = storage_path("app/public/events/{$request->event_id}/{$fileName}");
                    IndexFaceJob::dispatch($request->event_id, $absPath);
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

            $event = Event::where('id', $content->event_id)
                ->where('user_id', Auth::id())
                ->first();

            if (! $event) {
                return $this->errorResponse('You are not authorized to delete this content.', 403);
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

            // 2. Fetch the contents manually
            $contents = EventContents::where('event_id', $event->id)->get();

            // 3. Structure the data: Event Details first, then Contents
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

    public function generateEventPassword(){
        $password = $this->eventService->generatePassword();
        return $this->successResponse(['password' => $password], 'Event password generated successfully', 200);
    }

    public function setEventPassword(Request $request, $eventId){
        $request->validate([
            'password' => 'required|string|min:6|max:6',
        ]);

        $event = Event::where('id', $eventId)
                ->where('user_id', Auth::id())
                ->first();

        if (! $event) {
            return $this->errorResponse('Event not found or you are not authorized to edit it.', 404);
        }

        try {
            $this->eventService->setPasswordForEvent($event, $request->password);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }

        return $this->successResponse($event, 'Event password set successfully', 200);
    }

}
