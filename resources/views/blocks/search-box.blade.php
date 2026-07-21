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

    // '*' = cross-collection search over the site-level manifest (v3).
    $isCross = ($data['collectionId'] ?? null) === '*';
    $collection = (!$isCross && !empty($data['collectionId'])) ? \App\Models\ContentCollection::find($data['collectionId']) : ($isCross ? null : ($__collection ?? null));
    // Data source resolved at publish by tier (static index vs public API).
    [$csMode, $source] = $isCross ? ['static', '/search/index.json'] : ($collection ? RecordDisplay::searchSource($collection, $site) : ['static', '']);
    $csKey = $isCross ? '_site' : $collection?->slug;
    $placeholder = trim((string) ($data['placeholder'] ?? '')) ?: ($collection ? "Search {$collection->name}…" : 'Search…');
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="search-box-block cs-island {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}"
     data-cs-role="search-box"
     @if($csKey) data-cs-collection="{{ $csKey }}" data-cs-source="{{ $source }}" data-cs-mode="{{ $csMode }}" @endif
     @if($__htmlId) id="{{ $__htmlId }}" @endif>
@if(!$csKey)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a collection for this search box.</p>
@else
    <input type="search" name="q" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" autocomplete="off"
           style="width:100%;max-width:520px;padding:.65rem .9rem;font-size:1rem;border:1px solid var(--color-border,#ccc);background:var(--color-surface,#fff);color:inherit;">
    <noscript><p style="font-size:.85rem;opacity:.7;margin-top:.35rem;">Search needs JavaScript — browse the full list below.</p></noscript>
@endif
</div>
