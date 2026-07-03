<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Menus\Services\MenuService;
use App\Domain\Publishing\Services\AutoPublishService;
use App\Domain\References\Services\ReferenceRecorder;
use App\Domain\References\Services\ReferenceUsageService;
use App\Domain\References\Services\StalenessResolver;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct(
        private MenuService $menuService,
        private AutoPublishService $autoPublish,
        private StalenessResolver $staleness,
        private ReferenceRecorder $references,
        private ReferenceUsageService $usage,
    ) {}

    public function index(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $menus = $site->menus()->withCount('items')->get();

        return response()->json(['data' => $menus]);
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'in:header,footer,sidebar,mobile'],
        ]);

        $menu = $this->menuService->createMenu($request->only(['name', 'location']), $site);

        // A located menu renders on every page — keep site-scope edges current
        $this->references->recomputeSiteScope($site);

        return response()->json(['data' => $menu], 201);
    }

    public function show(Site $site, Menu $menu): JsonResponse
    {
        $this->authorize('view', $site);

        $tree = $this->menuService->getTree($menu);

        return response()->json(['data' => ['menu' => $menu, 'items' => $tree]]);
    }

    public function update(Request $request, Site $site, Menu $menu): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'in:header,footer,sidebar,mobile'],
            'style' => ['sometimes', 'array'],
            'style.bgColor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'style.textColor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'style.hoverColor' => ['sometimes', 'nullable', 'string', 'max:50'],
            'style.fontSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'style.fontWeight' => ['sometimes', 'nullable', 'in:,400,500,600,700'],
            'style.height' => ['sometimes', 'nullable', 'string', 'max:20'],
            'style.gap' => ['sometimes', 'nullable', 'string', 'max:20'],
            'style.letterSpacing' => ['sometimes', 'nullable', 'string', 'max:20'],
            'style.textTransform' => ['sometimes', 'nullable', 'in:,uppercase,lowercase,capitalize'],
            'style.sticky' => ['sometimes', 'boolean'],
            'style.transparent' => ['sometimes', 'boolean'],
            'style.showSearch' => ['sometimes', 'boolean'],
            'style.showSocial' => ['sometimes', 'boolean'],
        ]);

        $menu = $this->menuService->updateMenu($menu, $request->only(['name', 'location', 'style']));

        // Location/style changes alter rendered output; location moves also
        // change which pages carry the menu — refresh site-scope edges
        $this->references->recomputeSiteScope($site);
        $affected = $this->staleness->markStale($site, 'menu', $menu->id, "Menu '{$menu->name}' updated");

        return response()->json(['data' => $menu, 'meta' => ['stale' => $affected]]);
    }

    public function destroy(Request $request, Site $site, Menu $menu): JsonResponse
    {
        $this->authorize('update', $site);

        // Delete protection: block deletion while pages still reference the
        // menu, unless the caller explicitly forces it
        $usage = $this->usage->usage($site, 'menu', $menu->id);
        if ($usage['count'] > 0 && !$request->boolean('force')) {
            return response()->json([
                'message' => "Menu '{$menu->name}' is still in use. Pass force=1 to delete anyway.",
                'usedOnCount' => $usage['count'],
                'sources' => $usage['sources'],
            ], 409);
        }

        $menuName = $menu->name;
        $menuId = $menu->id;
        $this->menuService->deleteMenu($menu);

        if ($usage['count'] > 0) {
            $this->staleness->markStale($site, 'menu', $menuId, "Menu '{$menuName}' deleted (was in use)");
        }
        $this->references->recomputeSiteScope($site);

        return response()->json(null, 204);
    }

    /**
     * Sync the full menu item tree.
     */
    public function syncItems(Request $request, Site $site, Menu $menu): JsonResponse
    {
        $this->authorize('update', $site);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'items.*.page_id' => ['sometimes', 'nullable', 'uuid'],
            'items.*.post_id' => ['sometimes', 'nullable', 'uuid'],
            'items.*.category_id' => ['sometimes', 'nullable', 'uuid'],
            'items.*.target' => ['sometimes', 'string', 'in:_self,_blank'],
            'items.*.sort_order' => ['sometimes', 'integer'],
            'items.*.children' => ['sometimes', 'array'],
        ]);

        $menu = $this->menuService->syncItems($menu, $request->input('items'));
        $tree = $this->menuService->getTree($menu);

        // Flag affected pages (embedding blocks + site-wide when located)…
        $affected = $this->staleness->markStale($site, 'menu', $menu->id, "Menu '{$menu->name}' updated");

        // …then the full rebuild (when auto-publish is on) clears flags on success
        $this->autoPublish->triggerIfEnabled($site, $request->user(), 'menu');

        return response()->json(['data' => ['menu' => $menu, 'items' => $tree], 'meta' => ['stale' => $affected]]);
    }
}
