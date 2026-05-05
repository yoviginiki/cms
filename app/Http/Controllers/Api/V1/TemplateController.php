<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sites\Services\SiteCloneService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct(private SiteCloneService $cloneService) {}

    public function index(Request $request): JsonResponse
    {
        $query = SiteTemplate::query();

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Show public templates + own tenant's templates
        $tenantId = $request->user()->tenant_id;
        $query->where(function ($q) use ($tenantId) {
            $q->where('is_public', true)
                ->orWhere('is_system', true)
                ->orWhere('tenant_id', $tenantId);
        });

        $templates = $query->select([
            'id', 'name', 'description', 'category', 'preview_image',
            'page_count', 'is_public', 'is_system', 'created_at',
        ])->orderByDesc('created_at')->paginate(20);

        return response()->json($templates);
    }

    public function preview(SiteTemplate $template): JsonResponse
    {
        $data = $template->template_data;

        return response()->json([
            'data' => [
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'preview_image' => $template->preview_image,
                'page_count' => count($data['pages'] ?? []),
                'post_count' => count($data['posts'] ?? []),
                'category_count' => count($data['categories'] ?? []),
                'pages' => collect($data['pages'] ?? [])->pluck('title')->toArray(),
            ],
        ]);
    }

    public function install(Request $request, SiteTemplate $template, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $result = $this->cloneService->importFromTemplate(
            json_encode($template->template_data),
            $site
        );

        return response()->json(['data' => $result->toArray()]);
    }
}
