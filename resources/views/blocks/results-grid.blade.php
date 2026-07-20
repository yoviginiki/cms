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
    [$csMode, $source] = $collection ? RecordDisplay::searchSource($collection, $site) : ['static', ''];
    $columns = max(1, min(6, (int) ($data['columns'] ?? 3)));
    $showImage = $data['showImage'] ?? true;
    $emptyText = trim((string) ($data['emptyText'] ?? '')) ?: 'No results — try a different search.';
    $cardFields = [];
    if ($collection) {
        foreach ((array) ($data['cardFields'] ?? []) as $fk) {
            if (is_string($fk) && ($f = $collection->field($fk))) { $cardFields[] = $f; }
        }
    }
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="results-grid-block cs-island {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}"
     data-cs-role="results"
     @if($collection) data-cs-collection="{{ $collection->slug }}" data-cs-source="{{ $source }}" data-cs-mode="{{ $csMode }}" @endif
     @if(!empty($data['eager'])) data-cs-eager @endif
     @if($__htmlId) id="{{ $__htmlId }}" @endif>
@if(!$collection)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a collection for this results grid.</p>
@else
    <p class="cs-status" role="status" aria-live="polite" style="font-size:.85rem;opacity:.6;margin:0 0 .8rem;"></p>
    <div class="cs-results" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min({{ intval(720 / $columns) }}px,100%),1fr));gap:1.5rem;"></div>
    <p class="cs-empty" hidden style="opacity:.6;padding:2rem 0;text-align:center;">{{ $emptyText }}</p>
    {{-- Mustache-grade card template: the island clones it and fills the
         data-cs-* slots. No framework in published output. --}}
    <template data-cs-card>
        <article class="record-card" style="border:1px solid var(--color-border,#e5e2dd);background:var(--color-surface,#fff);overflow:hidden;">
            @if($showImage)
            <a data-cs-slot="url" style="display:block;">
                <div style="position:relative;width:100%;padding-top:66%;overflow:hidden;">
                    <img data-cs-slot="image" alt="" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                </div>
            </a>
            @endif
            <div style="padding:1rem 1.25rem;">
                <h3 style="margin:0 0 .4rem;font-size:1.05rem;"><a data-cs-slot="url" data-cs-slot-text="title" style="color:inherit;text-decoration:none;"></a></h3>
                @foreach($cardFields as $f)
                    <div data-cs-slot-field="{{ $f['key'] }}" data-cs-field-type="{{ $f['type'] }}" style="font-size:.9rem;margin:.15rem 0;color:var(--color-text-muted,#6b6864);"></div>
                @endforeach
            </div>
        </article>
    </template>
@endif
</div>
