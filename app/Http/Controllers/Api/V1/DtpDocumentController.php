<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Services\DtpDocumentService;
use App\Domain\Magazine\Services\MagazineReferenceExtractor;
use App\Domain\References\Services\ReferenceRecorder;
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
    public function show(Site $site, MagazineIssue $issue): JsonResponse
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        return response()->json([
            'data' => $this->documentService->loadDocument($issue),
        ]);
    }

    /**
     * Save full DTP document (atomic replace).
     */
    public function save(SaveDtpDocumentRequest $request, Site $site, MagazineIssue $issue): JsonResponse
    {
        $this->authorize('update', $site);
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        $document = $this->documentService->saveDocument($issue, $request->validated());

        // W3: record asset usage into the entity_references graph so magazines
        // get delete-protection + staleness like every other content type.
        // Reference recording must never fail a save.
        try {
            $edges = app(MagazineReferenceExtractor::class)->extract($issue->refresh());
            app(ReferenceRecorder::class)->persistEdges($site->id, 'magazine_doc', $issue->id, $edges);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('magazine reference recording failed: ' . $e->getMessage());
        }

        return response()->json([
            'data' => $document,
        ]);
    }
}
