<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Collections\Services\AppScaffolder;
use App\Http\Controllers\Controller;
use App\Models\ContentCollection;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * S6 — Database / Search / App wizards. Deterministic, no AI: each endpoint
 * composes AppScaffolder primitives (which wrap the existing collection /
 * page / block / template services). Admin/owner only (route middleware).
 */
class AppWizardController extends Controller
{
    public function __construct(private AppScaffolder $scaffolder) {}

    /** Database Wizard — create a batch of collections + relations + hierarchy. */
    public function database(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $specs = $this->validateCollections($request);

        $created = $this->scaffolder->createCollections($site, $specs);

        return response()->json(['data' => [
            'collections' => collect($created)->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->values(),
        ]], 201);
    }

    /** Search Wizard — set search flags on a collection + build a search page. */
    public function search(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $validated = $request->validate([
            'collection_id' => ['required', 'uuid'],
            'searchable' => ['sometimes', 'array'],
            'searchable.*' => ['string', 'max:40'],
            'facets' => ['sometimes', 'array', 'max:8'],
            'facets.*' => ['string', 'max:40'],
            'build_page' => ['sometimes', 'boolean'],
            'page_title' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $collection = ContentCollection::where('site_id', $site->id)->find($validated['collection_id']);
        if (!$collection) {
            throw ValidationException::withMessages(['collection_id' => 'Collection not found on this site.']);
        }

        $collection = $this->scaffolder->configureSearch(
            $site, $collection,
            $validated['searchable'] ?? [],
            $validated['facets'] ?? [],
        );

        $page = null;
        if ($request->boolean('build_page', true)) {
            $page = $this->scaffolder->buildSearchPage(
                $site, $collection,
                $validated['page_title'] ?? "Search {$collection->name}",
                $validated['facets'] ?? [],
                $this->defaultCardFields($collection),
            );
        }

        return response()->json(['data' => [
            'collection_id' => $collection->id,
            'page' => $page ? ['id' => $page->id, 'slug' => $page->slug] : null,
        ]], 201);
    }

    /**
     * App Wizard — the full flow: create collections, then for each chosen
     * one build a record-single template + an index page, plus one search
     * page. Returns everything scaffolded so the UI can link to it.
     */
    public function app(Request $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);
        $specs = $this->validateCollections($request);
        $request->validate([
            'pages_for' => ['sometimes', 'array'],
            'pages_for.*' => ['string'],
            'search_for' => ['sometimes', 'nullable', 'string'],
        ]);

        $created = $this->scaffolder->createCollections($site, $specs);

        $pagesFor = collect($request->input('pages_for', array_keys($created)))
            ->map(fn ($n) => mb_strtolower((string) $n));
        $result = ['collections' => [], 'templates' => [], 'index_pages' => [], 'search_page' => null];

        foreach ($created as $key => $collection) {
            $result['collections'][] = ['id' => $collection->id, 'name' => $collection->name];
            if (!$pagesFor->contains($key)) {
                continue;
            }
            $template = $this->scaffolder->buildRecordTemplate($site, $collection, $request->user()?->id);
            $index = $this->scaffolder->buildIndexPage($site, $collection, $collection->name);
            $result['templates'][] = ['id' => $template->id, 'collection' => $collection->name];
            $result['index_pages'][] = ['id' => $index->id, 'slug' => $index->slug, 'collection' => $collection->name];
        }

        $searchFor = mb_strtolower((string) $request->input('search_for', ''));
        $searchCollection = $created[$searchFor] ?? null;
        if ($searchCollection) {
            $searchable = collect($searchCollection->fields())
                ->filter(fn ($f) => in_array($f['type'], ['text', 'sku', 'rich_text'], true))
                ->pluck('key')->all();
            $facets = collect($searchCollection->fields())
                ->filter(fn ($f) => in_array($f['type'], ['select', 'multi_select', 'boolean', 'relation'], true))
                ->pluck('key')->take(4)->all();
            $searchCollection = $this->scaffolder->configureSearch($site, $searchCollection, $searchable, $facets);
            $page = $this->scaffolder->buildSearchPage(
                $site, $searchCollection, "Search {$searchCollection->name}",
                $facets, $this->defaultCardFields($searchCollection),
            );
            $result['search_page'] = ['id' => $page->id, 'slug' => $page->slug];
        }

        return response()->json(['data' => $result], 201);
    }

    /** @return array<int, array> validated collection specs */
    private function validateCollections(Request $request): array
    {
        $request->validate([
            'collections' => ['required', 'array', 'min:1', 'max:15'],
            'collections.*.name' => ['required', 'string', 'max:120'],
            'collections.*.tier' => ['sometimes', 'in:static,dynamic'],
            'collections.*.hierarchical' => ['sometimes', 'boolean'],
            'collections.*.fields' => ['required', 'array', 'min:1', 'max:30'],
            'collections.*.fields.*.label' => ['required', 'string', 'max:255'],
            'collections.*.fields.*.type' => ['required', 'string'],
            'collections.*.fields.*.target' => ['sometimes', 'nullable', 'string', 'max:120'],
            'collections.*.fields.*.mode' => ['sometimes', 'in:one,many'],
            'collections.*.fields.*.options' => ['sometimes', 'array'],
        ]);

        return $request->input('collections');
    }

    private function defaultCardFields(ContentCollection $collection): array
    {
        return collect($collection->fields())
            ->filter(fn ($f) => in_array($f['type'], ['price', 'select', 'text'], true) && $f['key'] !== $collection->titleField())
            ->pluck('key')->take(2)->all();
    }
}
