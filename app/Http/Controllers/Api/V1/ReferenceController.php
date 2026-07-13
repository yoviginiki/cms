<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\References\Services\ReferenceUsageService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only access to the entity-reference graph: "used on N pages"
 * counts and referring-source lists for any target entity.
 */
class ReferenceController extends Controller
{
    public function __construct(private ReferenceUsageService $usage)
    {
    }

    public function usage(Request $request, Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $validated = $request->validate([
            'target_type' => ['required', 'string', 'in:asset,menu,page,post,category,theme,slider,magazine_doc,global_section,style_preset,collection,record'],
            'target_id' => ['required', 'uuid'],
        ]);

        return response()->json([
            'data' => $this->usage->usage($site, $validated['target_type'], $validated['target_id']),
        ]);
    }
}
