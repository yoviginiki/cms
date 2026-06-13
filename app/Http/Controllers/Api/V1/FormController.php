<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Forms\Services\FormSubmissionService;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct(private FormSubmissionService $formService) {}

    /**
     * List form submissions (auth required).
     */
    public function submissions(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);
        $limit = $request->integer('limit', 50);
        return response()->json(['data' => $this->formService->getSubmissions($site->id, $limit)]);
    }

    /**
     * Delete a submission (auth required).
     */
    public function deleteSubmission(Request $request, Site $site, string $index): JsonResponse
    {
        $this->authorize('update', $site);
        $deleted = $this->formService->deleteSubmission($site->id, (int) $index);
        return response()->json(['success' => $deleted]);
    }

    /**
     * Public endpoint — no auth required, rate-limited.
     */
    public function submit(Request $request, Site $site): JsonResponse
    {
        // Find the contact form block to get the recipient email
        $formBlock = Block::whereHasMorph('blockable', ['page', 'post'], function ($q) use ($site) {
            $q->where('site_id', $site->id);
        })->where('type', 'contact-form')->first();

        if (!$formBlock) {
            return response()->json(['message' => 'No contact form configured'], 404);
        }

        $recipientEmail = $formBlock->data['recipient_email'] ?? null;
        if (!$recipientEmail) {
            return response()->json(['message' => 'Form not configured'], 500);
        }

        $success = $this->formService->submit($site->id, $request->all(), $recipientEmail);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Message sent successfully' : 'Failed to send message',
        ], $success ? 200 : 500);
    }
}
