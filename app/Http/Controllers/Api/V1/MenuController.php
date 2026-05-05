<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Menus\Services\MenuService;
use App\Domain\Publishing\Services\AutoPublishService;
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
        ]);

        $menu = $this->menuService->updateMenu($menu, $request->only(['name', 'location']));

        return response()->json(['data' => $menu]);
    }

    public function destroy(Site $site, Menu $menu): JsonResponse
    {
        $this->authorize('update', $site);

        $this->menuService->deleteMenu($menu);

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

        // Menu changes affect ALL pages — full rebuild
        $this->autoPublish->triggerIfEnabled($site, $request->user(), 'menu');

        return response()->json(['data' => ['menu' => $menu, 'items' => $tree]]);
    }
}
