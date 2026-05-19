<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Services\DtpDocumentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDtpDocumentRequest;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

class DtpDocumentController extends Controller
{
    public function __construct(
        private DtpDocumentService $documentService,
    ) {}

    /**
     * Load full DTP document for an issue.
     */
    public function show(Site $site, string $issueId): JsonResponse
    {
        $issue = MagazineIssue::where('site_id', $site->id)->findOrFail($issueId);

        return response()->json([
            'data' => $this->documentService->loadDocument($issue),
        ]);
    }

    /**
     * Save full DTP document (atomic replace).
     */
    public function save(SaveDtpDocumentRequest $request, Site $site, string $issueId): JsonResponse
    {
        $issue = MagazineIssue::where('site_id', $site->id)->findOrFail($issueId);

        $document = $this->documentService->saveDocument($issue, $request->validated());

        return response()->json([
            'data' => $document,
        ]);
    }
}
