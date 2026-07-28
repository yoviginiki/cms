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
        $direct = \App\Models\Record::where('collection_id', $collection->id)
            ->where('status', 'published')
            ->whereNotNull('category_node_id')
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
            <a href="{{ RecordDisplay::categoryUrl($collection, $node) }}"
               style="display:inline-flex;align-items:center;gap:.5em;padding:.45em 1em;border:1px solid var(--color-border-light,#e5e5e0);border-radius:999px;text-decoration:none;color:var(--color-heading,#1a202c);font-size:.9em;transition:border-color .2s ease,background .2s ease;"
               onmouseover="this.style.borderColor='var(--color-primary,#3b82f6)'" onmouseout="this.style.borderColor='var(--color-border-light,#e5e5e0)'">
                {{ $node->name }}
                @if($showCount)<span style="opacity:.5;font-size:.85em;">{{ $counts[$node->id] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </div>
@elseif($layout === 'list')
    <div class="cc-list" style="display:flex;flex-direction:column;gap:{{ $gap }};">
        @foreach($nodes as $node)
            <a href="{{ RecordDisplay::categoryUrl($collection, $node) }}"
               style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.85rem 1.1rem;border:1px solid var(--color-border-light,#e5e5e0);border-radius:12px;text-decoration:none;color:var(--color-heading,#1a202c);transition:border-color .2s ease,transform .2s ease;"
               onmouseover="this.style.borderColor='var(--color-primary,#3b82f6)'" onmouseout="this.style.borderColor='var(--color-border-light,#e5e5e0)'">
                <span style="font-weight:600;">{{ $node->name }}</span>
                @if($showCount)<span style="opacity:.5;font-size:.85em;white-space:nowrap;">{{ $counts[$node->id] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </div>
@else
    <div class="cc-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min({{ intval(960 / $columns) }}px,100%),1fr));gap:{{ $gap }};">
        @foreach($nodes as $node)
            <a href="{{ RecordDisplay::categoryUrl($collection, $node) }}"
               style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:24px 16px;background:var(--color-bg,#fff);border:1px solid var(--color-border-light,rgba(26,32,44,.08));border-radius:16px;text-decoration:none;color:var(--color-heading,#1a202c);text-align:center;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease;"
               onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 28px -12px rgba(0,0,0,.18)';this.style.borderColor='var(--color-primary,#3b82f6)'"
               onmouseout="this.style.transform='';this.style.boxShadow='';this.style.borderColor='var(--color-border-light,rgba(26,32,44,.08))'">
                <span style="width:48px;height:48px;border-radius:12px;background:color-mix(in srgb,var(--color-primary,#3b82f6) 10%,transparent);display:inline-flex;align-items:center;justify-content:center;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary,#3b82f6)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                </span>
                <span style="font-weight:600;font-size:.95em;line-height:1.35;">{{ $node->name }}</span>
                @if($showCount)<span style="opacity:.5;font-size:.8em;">{{ $counts[$node->id] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </div>
@endif
</div>
