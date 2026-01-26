<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Faq, Page};
use App\Traits\ApiResponseTraits;

class PageController extends Controller
{
    use ApiResponseTraits;
    public function CreateOrUpdatePage(Request $request)
    {
        $request->validate([
            'key' => 'required|string', // e.g. "terms", "privacy", "about"
            'value' => 'required|string', // The content
        ]);

        $page = Page::updateOrCreate(
            ['key' => $request->key],
            ['value' => $request->value]
        );

       return $this->successResponse([
            'page' => $page
        ], 'Page created/updated successfully.', 200);
    }

    public function PageShow(Request $request, $key = null)
    {
        $lookupKey = $key ?? $request->query('key');

        if (!$lookupKey) {
            return $this->errorResponse('Missing required parameter: key', 400);
        }    
        $page = Page::where('key', $lookupKey)->first();

        if (!$page) {
            return $this->errorResponse('No content found for '.$lookupKey.'',404);
        }

        return $this->successResponse([
            'page' => $page
        ], 'Page retrieved successfully.', 200);
    }

    public function index()
    {
        $faqs = Faq::paginate(10);

        return response()->json(['status' => true, 'data' => $faqs]);
    }

    /**
     * Create FAQ (Admin Only)
     * POST /api/faqs
     */
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
        ]);

        $faq = Faq::create([
            'question' => $request->question,
            'answer' => $request->answer,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'FAQ added successfully',
            'data' => $faq,
        ]);
    }

    /**
     * Update FAQ (Admin Only)
     * PUT /api/faqs/{id}
     */
    public function update(Request $request, $id)
    {
        $faq = Faq::findOrFail($id);

        if (! $faq) {
            return response()->json(['success' => false, 'message' => 'FAQ not found'], 404);
        }

        $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
        ]);

        $faq->update([
            'question' => $request->question,
            'answer' => $request->answer,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'FAQ updated successfully',
            'data' => $faq,
        ]);
    }

    /**
     * Delete FAQ (Admin Only)
     * DELETE /api/faqs/{id}
     */
    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);

        $faq->delete();

        return response()->json([
            'status' => true,
            'message' => 'FAQ deleted successfully',
        ]);
    }

}
