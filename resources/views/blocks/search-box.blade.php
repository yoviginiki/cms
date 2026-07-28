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
    [$csMode, $source] = $isCross ? ['static', RecordDisplay::sitePathBase($site) . '/search/index.json'] : ($collection ? RecordDisplay::searchSource($collection, $site, $__locale ?? null) : ['static', '']);
    $csKey = $isCross ? '_site' : $collection?->slug;
    // Locale-aware defaults (bg default site, en for the /en/ tree).
    $__pl = $__locale ?? null;
    $placeholder = trim((string) ($data['placeholder'] ?? '')) ?: ($__pl === 'en' ? 'Search…' : 'Търсене…');
    $buttonLabel = trim((string) ($data['buttonLabel'] ?? '')) ?: ($__pl === 'en' ? 'Search' : 'Търсене');
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="search-box-block cs-island {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}"
     data-cs-role="search-box"
     @if($csKey) data-cs-collection="{{ $csKey }}" data-cs-source="{{ $source }}" data-cs-mode="{{ $csMode }}" @endif
     @if($__htmlId) id="{{ $__htmlId }}" @endif>
@if(!$csKey)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a collection for this search box.</p>
@else
    <form class="cs-searchbar" role="search" onsubmit="return false" style="display:flex;gap:.6rem;max-width:600px;align-items:stretch;margin-bottom:1.5rem;">
        <input type="search" name="q" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" autocomplete="off"
               style="flex:1;min-width:0;padding:.85rem 1.1rem;font-size:1.05rem;border:1px solid var(--color-border,#ccc);background:var(--color-surface,#fff);color:inherit;border-radius:10px;">
        <button type="submit" data-cs-submit aria-label="{{ $buttonLabel }}"
                style="flex-shrink:0;display:inline-flex;align-items:center;gap:.45rem;padding:.85rem 1.5rem;font-size:1.05rem;font-weight:600;border:0;border-radius:10px;background:var(--color-primary,#3b82f6);color:#fff;cursor:pointer;transition:background .2s,transform .15s;"
                onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform=''">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path></svg>
            <span>{{ $buttonLabel }}</span>
        </button>
    </form>
    <noscript><p style="font-size:.85rem;opacity:.7;margin-top:.35rem;">Search needs JavaScript — browse the full list below.</p></noscript>
@endif
</div>
