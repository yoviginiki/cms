<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Magazine\Models\MagStyle;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MagStyleController extends Controller
{
    /**
     * GET /sites/{site}/magazine-styles
     */
    public function index(Site $site): JsonResponse
    {
        $styles = MagStyle::where('site_id', $site->id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $styles]);
    }

    /**
     * POST /sites/{site}/magazine-styles
     */
    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:paragraph,character,object,table,cell',
            'properties' => 'required|array',
            'based_on' => 'sometimes|nullable|uuid',
            'next_style' => 'sometimes|nullable|uuid',
            'sort_order' => 'sometimes|integer',
            'is_default' => 'sometimes|boolean',
        ]);

        $style = MagStyle::create([
            'site_id' => $site->id,
            ...$validated,
        ]);

        return response()->json(['data' => $style], 201);
    }

    /**
     * GET /sites/{site}/magazine-styles/{style}
     */
    public function show(Site $site, MagStyle $style): JsonResponse
    {
        return response()->json(['data' => $style]);
    }

    /**
     * PUT /sites/{site}/magazine-styles/{style}
     */
    public function update(Request $request, Site $site, MagStyle $style): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:paragraph,character,object,table,cell',
            'properties' => 'sometimes|array',
            'based_on' => 'sometimes|nullable|uuid',
            'next_style' => 'sometimes|nullable|uuid',
            'sort_order' => 'sometimes|integer',
            'is_default' => 'sometimes|boolean',
        ]);

        $style->update($validated);

        return response()->json(['data' => $style]);
    }

    /**
     * DELETE /sites/{site}/magazine-styles/{style}
     */
    public function destroy(Site $site, MagStyle $style): JsonResponse
    {
        $this->authorize('update', $site);
        $style->delete();

        return response()->json(['message' => 'Style deleted']);
    }
}
