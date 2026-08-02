<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Projection\Export\ProjectionExporter;
use App\Domain\Projection\ProjectionPublisher;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Export consumer — HTTP delivery. Read-only projection of a page/post as JSON
 * or Markdown. Lives inside the authenticated, tenant-scoped route group; it
 * builds the projection fresh and never writes.
 */
class ProjectionExportController extends Controller
{
    public function __construct(
        private ProjectionPublisher $publisher,
        private ProjectionExporter $exporter,
    ) {
    }

    public function page(Request $request, Site $site, Page $page): Response
    {
        return $this->export($request, $site, $page);
    }

    public function post(Request $request, Site $site, Post $post): Response
    {
        return $this->export($request, $site, $post);
    }

    private function export(Request $request, Site $site, object $content): Response
    {
        $format = strtolower((string) $request->query('format', 'json'));

        $url = '/' . trim((string) ($content->slug ?? ''), '/');
        $url = $url === '/' ? '/' : $url . '/';
        $projection = $this->publisher->build($site, $content, $url);

        if ($format === 'md') {
            return response(
                $this->exporter->toMarkdown($projection),
                200,
                ['Content-Type' => 'text/markdown; charset=UTF-8']
            );
        }

        if ($format !== 'json') {
            abort(422, "Unknown format '{$format}' (expected json|md).");
        }

        return response(
            $this->exporter->toJson($projection, false),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
