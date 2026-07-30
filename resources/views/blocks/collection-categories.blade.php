@use('App\Support\Blocks\BlockStyle')
@use('App\Support\Blocks\RecordDisplay')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba, $data ?? []);
    $__customClass = BlockStyle::buildClasses($__adv, $__ba);
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%)$/i', trim((string) $v)) ? trim((string) $v) : '';

    // Collection: explicit on the block, else the archive/template context's.
    $collection = null;
    if (!empty($data['collectionId'])) {
        $collection = \App\Models\ContentCollection::find($data['collectionId']);
    } elseif (isset($__collection)) {
        $collection = $__collection;
    }

    // Page locale (from the build context) drives link prefixes, display
    // names and counts; default language links carry no prefix.
    $__pageLocale = $__locale ?? null;
    $__defaultLocale = \App\Domain\Publishing\Services\LocalePaths::defaultLanguage($site);
    $__urlLocale = ($__pageLocale && $__pageLocale !== $__defaultLocale) ? $__pageLocale : null;
    $__localeKey = $collection ? RecordDisplay::localeField($collection) : null;

    $nodes = collect();
    $counts = [];
    if ($collection) {
        // Parent: explicit node on the block, else the current category-page
        // context node (so the same block shows subcategories when reused on
        // category pages), else the root level.
        $parentId = $data['parentNodeId'] ?? null;
        if (!$parentId && isset($__categoryNode)) {
            $parentId = $__categoryNode->id;
        }
        if ($parentId && !\App\Models\CollectionCategoryNode::where('collection_id', $collection->id)->find($parentId)) {
            $parentId = null; // node deleted — fall back to roots
        }

        $all = \App\Models\CollectionCategoryNode::where('collection_id', $collection->id)
            ->orderBy('sort_order')->orderBy('name')
            ->get();
        $byParent = $all->groupBy(fn($n) => $n->parent_id ?? '');
        $nodes = $byParent->get($parentId ?? '', collect());

        // Published-record counts per node, then rolled up the subtree so a
        // parent card counts everything beneath it.
        $directQuery = \App\Models\Record::where('collection_id', $collection->id)
            ->where('status', 'published')
            ->whereNotNull('category_node_id');
        if ($__localeKey && $__pageLocale) {
            $__pageLocale === $__defaultLocale
                ? $directQuery->whereRaw("(data->>? = ? or data->>? is null)", [$__localeKey, $__pageLocale, $__localeKey])
                : $directQuery->whereRaw("data->>? = ?", [$__localeKey, $__pageLocale]);
        }
        $direct = $directQuery
            ->selectRaw('category_node_id, count(*) as c')
            ->groupBy('category_node_id')
            ->pluck('c', 'category_node_id');
        $subtreeCount = function ($nodeId) use (&$subtreeCount, $byParent, $direct) {
            $total = (int) ($direct[$nodeId] ?? 0);
            foreach ($byParent->get($nodeId, collect()) as $child) {
                $total += $subtreeCount($child->id);
            }
            return $total;
        };
        foreach ($nodes as $n) {
            $counts[$n->id] = $subtreeCount($n->id);
        }
        if ($data['hideEmpty'] ?? false) {
            $nodes = $nodes->filter(fn($n) => ($counts[$n->id] ?? 0) > 0)->values();
        }
    }

    $layout = in_array($data['layout'] ?? 'cards', ['cards', 'pills', 'list'], true) ? ($data['layout'] ?? 'cards') : 'cards';
    $columns = max(2, min(6, (int) ($data['columns'] ?? 4)));
    $gap = $cssDim($data['gap'] ?? '') ?: '1rem';
    $showCount = $data['showCount'] ?? true;
    $showName = $data['showName'] ?? true;
    $showImage = $data['showImage'] ?? false;
    $showDescription = $data['showDescription'] ?? false;
    $imageHeight = $cssDim($data['imageHeight'] ?? '') ?: '140px';

    // Per-category image: use the node's own image (schema.image) if set, else
    // fall back to the first published product in the node's subtree — so
    // categories get a representative picture with zero manual work.
    $nodeImages = [];
    $nodeDesc = [];
    if ($collection && ($showImage || $showDescription)) {
        foreach ($nodes as $n) {
            $nodeDesc[$n->id] = trim((string) ($n->schema['description'] ?? ''));
            if (!$showImage) { continue; }
            // Resolve an image reference (asset id OR url) to a structured
            // record: servable URL + intrinsic width/height (for CLS) + webp
            // srcset (for byte savings) when it's a library asset.
            $resolveImg = function ($v) use ($site) {
                if (!is_string($v) || $v === '') { return null; }
                if (str_starts_with($v, '/') || str_starts_with($v, 'http')) {
                    return ['url' => $v, 'w' => null, 'h' => null, 'webp' => ''];
                }
                $base = RecordDisplay::assetUrl($site, $v);
                $asset = \App\Models\Asset::find($v);
                if (!$asset) { return ['url' => $base, 'w' => null, 'h' => null, 'webp' => '']; }
                $dim = (array) ($asset->dimensions ?? []);
                $variants = (array) ($asset->variants ?? []);
                $webp = [];
                foreach (['webp_400' => '400w', 'webp_800' => '800w'] as $vn => $desc) {
                    if (!empty($variants[$vn])) { $webp[] = "{$base}/{$vn} {$desc}"; }
                }
                // Small images (<=400px) skip the sized webp variants — fall back
                // to the source-resolution webp so they still ship as webp.
                $webpVal = $webp ? implode(', ', $webp) : (!empty($variants['webp']) ? "{$base}/webp" : '');
                return ['url' => $base, 'w' => $dim['width'] ?? null, 'h' => $dim['height'] ?? null, 'webp' => $webpVal];
            };
            $own = $resolveImg($n->schema['image'] ?? null);
            if ($own) { $nodeImages[$n->id] = $own; continue; }
            $ids = [$n->id];
            $collectIds = function ($pid) use (&$collectIds, $byParent, &$ids) {
                foreach ($byParent->get($pid, collect()) as $c) { $ids[] = $c->id; $collectIds($c->id); }
            };
            $collectIds($n->id);
            $rec = \App\Models\Record::where('collection_id', $collection->id)
                ->where('status', 'published')->whereIn('category_node_id', $ids)
                ->whereRaw("data->>'image' is not null and data->>'image' <> ''")
                ->orderBy('position')->first();
            if ($rec) { $nodeImages[$n->id] = $resolveImg($rec->data['image'] ?? null); }
        }
    }
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="collection-categories-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@if(!$collection)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a collection for this category list.</p>
@elseif($nodes->isEmpty())
    <p style="opacity:.6;">No categories yet.</p>
@elseif($layout === 'pills')
    <div class="cc-pills" style="display:flex;flex-wrap:wrap;gap:{{ $gap }};">
        @foreach($nodes as $node)
            <a href="{{ RecordDisplay::categoryUrl($collection, $node, $__urlLocale) }}"
               style="display:inline-flex;align-items:center;gap:.5em;padding:.45em 1em;border:1px solid var(--color-border-light,#e5e5e0);border-radius:999px;text-decoration:none;color:var(--color-heading,#1a202c);font-size:.9em;transition:border-color .2s ease,background .2s ease;"
               onmouseover="this.style.borderColor='var(--color-primary,#3b82f6)'" onmouseout="this.style.borderColor='var(--color-border-light,#e5e5e0)'">
                {{ RecordDisplay::nodeName($node, $__pageLocale) }}
                @if($showCount)<span style="opacity:.5;font-size:.85em;">{{ $counts[$node->id] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </div>
@elseif($layout === 'list')
    <div class="cc-list" style="display:flex;flex-direction:column;gap:{{ $gap }};">
        @foreach($nodes as $node)
            <a href="{{ RecordDisplay::categoryUrl($collection, $node, $__urlLocale) }}"
               style="display:flex;align-items:center;gap:1rem;padding:.75rem 1.1rem;border:1px solid var(--color-border-light,#e5e5e0);border-radius:12px;text-decoration:none;color:var(--color-heading,#1a202c);transition:border-color .2s ease,transform .2s ease;"
               onmouseover="this.style.borderColor='var(--color-primary,#3b82f6)'" onmouseout="this.style.borderColor='var(--color-border-light,#e5e5e0)'">
                @if($showImage && !empty($nodeImages[$node->id]))
                    @php $__ni = $nodeImages[$node->id]; @endphp
                    @if(!empty($__ni['webp']))<picture><source srcset="{{ $__ni['webp'] }}" type="image/webp"><img src="{{ e($__ni['url']) }}" alt="" loading="lazy" @if($__ni['w'])width="{{ $__ni['w'] }}"@endif @if($__ni['h'])height="{{ $__ni['h'] }}"@endif style="flex-shrink:0;width:{{ $imageHeight }};height:{{ $imageHeight }};object-fit:cover;border-radius:8px;"></picture>
                    @else<img src="{{ e($__ni['url']) }}" alt="" loading="lazy" @if($__ni['w'])width="{{ $__ni['w'] }}"@endif @if($__ni['h'])height="{{ $__ni['h'] }}"@endif style="flex-shrink:0;width:{{ $imageHeight }};height:{{ $imageHeight }};object-fit:cover;border-radius:8px;">@endif
                @endif
                <span style="flex:1;min-width:0;">
                    @if($showName)<span style="display:block;font-weight:600;">{{ RecordDisplay::nodeName($node, $__pageLocale) }}</span>@endif
                    @if($showDescription && !empty($nodeDesc[$node->id]))<span style="display:block;opacity:.7;font-size:.85em;line-height:1.4;margin-top:.15rem;">{{ $nodeDesc[$node->id] }}</span>@endif
                </span>
                @if($showCount)<span style="opacity:.5;font-size:.85em;white-space:nowrap;">{{ $counts[$node->id] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </div>
@else
    @php
        $ccUid = 'ccg-' . substr(md5(($__htmlId ?: '') . $collection->id . $layout), 0, 8);
        // Square-ish image: honor a numeric imageHeight if set, else square.
        $imgSquare = ($data['imageHeight'] ?? '') === '' ? 'aspect-ratio:1/1;height:auto' : 'height:' . $imageHeight;
    @endphp
    <style>
        .{{ $ccUid }}{grid-template-columns:repeat({{ $columns }},minmax(0,1fr));}
        @media(max-width:900px){.{{ $ccUid }}{grid-template-columns:repeat(2,minmax(0,1fr))!important;}}
        @media(max-width:560px){.{{ $ccUid }}{grid-template-columns:1fr!important;}}
    </style>
    <div class="cc-grid {{ $ccUid }}" style="display:grid;gap:{{ $gap }};">
        @foreach($nodes as $node)
            <a href="{{ RecordDisplay::categoryUrl($collection, $node, $__urlLocale) }}"
               style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:{{ $showImage && !empty($nodeImages[$node->id]) ? '0 0 22px' : '24px 16px' }};overflow:hidden;background:var(--color-bg,#fff);border:1px solid var(--color-border-light,rgba(26,32,44,.08));border-radius:16px;text-decoration:none;color:var(--color-heading,#1a202c);text-align:center;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease;"
               onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 28px -12px rgba(0,0,0,.18)';this.style.borderColor='var(--color-primary,#3b82f6)'"
               onmouseout="this.style.transform='';this.style.boxShadow='';this.style.borderColor='var(--color-border-light,rgba(26,32,44,.08))'">
                @if($showImage && !empty($nodeImages[$node->id]))
                    @php $__ni = $nodeImages[$node->id]; @endphp
                    @if(!empty($__ni['webp']))<picture><source srcset="{{ $__ni['webp'] }}" sizes="(max-width:560px) 88vw, (max-width:900px) 45vw, 24vw" type="image/webp"><img src="{{ e($__ni['url']) }}" alt="" loading="lazy" @if($__ni['w'])width="{{ $__ni['w'] }}"@endif @if($__ni['h'])height="{{ $__ni['h'] }}"@endif style="width:100%;{{ $imgSquare }};object-fit:cover;display:block;"></picture>
                    @else<img src="{{ e($__ni['url']) }}" alt="" loading="lazy" @if($__ni['w'])width="{{ $__ni['w'] }}"@endif @if($__ni['h'])height="{{ $__ni['h'] }}"@endif style="width:100%;{{ $imgSquare }};object-fit:cover;display:block;">@endif
                @elseif($showImage)
                    <span style="width:100%;aspect-ratio:1/1;background:var(--color-bg-alt,#f5f5f0);display:flex;align-items:center;justify-content:center;">
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary,#3b82f6)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                    </span>
                @else
                    <span style="width:48px;height:48px;margin-top:24px;border-radius:12px;background:color-mix(in srgb,var(--color-primary,#3b82f6) 10%,transparent);display:inline-flex;align-items:center;justify-content:center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary,#3b82f6)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                    </span>
                @endif
                @if($showName)<span style="font-weight:700;font-size:1.15rem;line-height:1.3;padding:0 14px;">{{ RecordDisplay::nodeName($node, $__pageLocale) }}</span>@endif
                @if($showDescription && !empty($nodeDesc[$node->id]))<span style="opacity:.7;font-size:.9rem;line-height:1.45;padding:0 16px;">{{ $nodeDesc[$node->id] }}</span>@endif
                @if($showCount)<span style="opacity:.5;font-size:.85rem;">{{ $counts[$node->id] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </div>
@endif
</div>
