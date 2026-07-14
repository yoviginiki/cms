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
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);

    $collection = !empty($data['collectionId']) ? \App\Models\ContentCollection::find($data['collectionId']) : ($__collection ?? null);
    // Tier-agnostic data source, resolved at render: static index manifest for
    // Tier 1; G3 swaps this to the API endpoint for dynamic collections.
    $source = $collection ? '/' . RecordDisplay::pathPrefix($collection) . '/index.json' : '';
    $placeholder = trim((string) ($data['placeholder'] ?? '')) ?: ($collection ? "Search {$collection->name}…" : 'Search…');
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="search-box-block cs-island {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}"
     data-cs-role="search-box"
     @if($collection) data-cs-collection="{{ $collection->slug }}" data-cs-source="{{ $source }}" @endif
     @if($__htmlId) id="{{ $__htmlId }}" @endif>
@if(!$collection)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a collection for this search box.</p>
@else
    <input type="search" name="q" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" autocomplete="off"
           style="width:100%;max-width:520px;padding:.65rem .9rem;font-size:1rem;border:1px solid var(--color-border,#ccc);background:var(--color-surface,#fff);color:inherit;">
    <noscript><p style="font-size:.85rem;opacity:.7;margin-top:.35rem;">Search needs JavaScript — browse the full list below.</p></noscript>
@endif
</div>
