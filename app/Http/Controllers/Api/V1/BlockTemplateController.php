<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BlockTemplate;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockTemplateController extends Controller
{
    public function index(Site $site): JsonResponse
    {
        $templates = BlockTemplate::where('site_id', $site->id)
            ->orWhere('is_system', true)
            ->orderByDesc('is_system')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'blocks_data' => ['required', 'array'],
        ]);

        $template = BlockTemplate::create([
            'site_id' => $site->id,
            'name' => $validated['name'],
            'category' => $validated['category'] ?? 'custom',
            'description' => $validated['description'] ?? null,
            'blocks_data' => $validated['blocks_data'],
            'is_system' => false,
        ]);

        return response()->json(['data' => $template], 201);
    }

    public function destroy(Site $site, BlockTemplate $template): JsonResponse
    {
        $this->authorize('update', $site);
        if ($template->is_system) {
            return response()->json(['message' => 'Cannot delete system templates.'], 403);
        }
        if ($template->site_id !== $site->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $template->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
