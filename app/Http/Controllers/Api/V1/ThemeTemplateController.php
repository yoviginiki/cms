<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ThemeTemplateController extends Controller
{
    /**
     * Abort if the template doesn't belong to this site.
     */
    private function assertOwnership(Site $site, ThemeTemplate $themeTemplate): void
    {
        if ($themeTemplate->site_id !== $site->id) {
            abort(404, 'Template not found');
        }
    }

    public function index(Site $site): JsonResponse
    {
        $templates = ThemeTemplate::where('site_id', $site->id)
            ->with('category:id,name,slug')
            ->orderBy('type')
            ->orderByDesc('priority')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function show(Site $site, ThemeTemplate $themeTemplate): JsonResponse
    {
        $this->assertOwnership($site, $themeTemplate);
        $themeTemplate->load('category:id,name,slug');
        return response()->json(['data' => $themeTemplate]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:post,archive,header,footer,404,search'],
            'category_id' => ['sometimes', 'nullable', 'uuid', "exists:categories,id,site_id,{$site->id}"],
            'post_format' => ['sometimes', 'nullable', 'in:standard,video,gallery,audio,link'],
            'is_default' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
        ]);

        $data['site_id'] = $site->id;
        $data['slug'] = Str::slug($data['name']);
        $data['created_by'] = $request->user()?->id;

        // Ensure unique slug
        $base = $data['slug'];
        $i = 1;
        while (ThemeTemplate::where('site_id', $site->id)->where('slug', $data['slug'])->exists()) {
            $data['slug'] = $base . '-' . $i++;
        }

        // If setting as default, unset other defaults of same type
        if (!empty($data['is_default'])) {
            ThemeTemplate::where('site_id', $site->id)
                ->where('type', $data['type'])
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $template = ThemeTemplate::create($data);

        return response()->json(['data' => $template], 201);
    }

    public function update(Request $request, Site $site, ThemeTemplate $themeTemplate): JsonResponse
    {
        $this->assertOwnership($site, $themeTemplate);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:post,archive,header,footer,404,search'],
            'category_id' => ['sometimes', 'nullable', 'uuid', "exists:categories,id,site_id,{$site->id}"],
            'post_format' => ['sometimes', 'nullable', 'in:standard,video,gallery,audio,link'],
            'is_default' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
        ]);

        if (!empty($data['is_default']) && !$themeTemplate->is_default) {
            ThemeTemplate::where('site_id', $site->id)
                ->where('type', $data['type'] ?? $themeTemplate->type)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $themeTemplate->update($data);

        return response()->json(['data' => $themeTemplate->fresh()]);
    }

    public function destroy(Site $site, ThemeTemplate $themeTemplate): JsonResponse
    {
        $this->assertOwnership($site, $themeTemplate);
        $themeTemplate->blocks()->delete();
        $themeTemplate->delete();

        return response()->json(['message' => 'Template deleted']);
    }
}
