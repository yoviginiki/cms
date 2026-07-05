<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\Magazine\Models\MagazineDocVersion;
use App\Domain\Magazine\Services\DtpDocumentService;
use App\Domain\Magazine\Services\MagazineReferenceExtractor;
use App\Domain\References\Services\ReferenceRecorder;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;

/** Version trail for DTP documents (W3): list snapshots, restore any of them. */
class DtpVersionController extends Controller
{
    public function __construct(private DtpDocumentService $documentService)
    {
    }

    public function index(Site $site, MagazineIssue $issue): JsonResponse
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }

        return response()->json([
            'data' => MagazineDocVersion::where('issue_id', $issue->id)
                ->orderByDesc('created_at')
                ->get(['id', 'label', 'page_count', 'frame_count', 'created_at']),
        ]);
    }

    public function restore(Site $site, MagazineIssue $issue, string $versionId): JsonResponse
    {
        if ($issue->site_id !== $site->id) {
            abort(404);
        }
        $version = MagazineDocVersion::where('issue_id', $issue->id)->findOrFail($versionId);

        // saveDocument snapshots the pre-restore state first, so a restore
        // is itself restorable.
        $document = $this->documentService->saveDocument($issue, $version->document);

        try {
            $edges = app(MagazineReferenceExtractor::class)->extract($issue->refresh());
            app(ReferenceRecorder::class)->persistEdges($site->id, 'magazine_doc', $issue->id, $edges);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('magazine reference recording failed: ' . $e->getMessage());
        }

        return response()->json(['data' => $document]);
    }
}
