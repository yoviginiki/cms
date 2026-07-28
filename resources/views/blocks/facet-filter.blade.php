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

    // A star = cross-collection search: one synthetic Type facet, filled by JS.
    $isCross = ($data['collectionId'] ?? null) === '*';
    $collection = (!$isCross && !empty($data['collectionId'])) ? \App\Models\ContentCollection::find($data['collectionId']) : ($isCross ? null : ($__collection ?? null));
    [$csMode, $source] = $isCross ? ['static', RecordDisplay::sitePathBase($site) . '/search/index.json'] : ($collection ? RecordDisplay::searchSource($collection, $site, $__locale ?? null) : ['static', '']);
    $csKey = $isCross ? '_site' : $collection?->slug;
    $style = in_array($data['style'] ?? 'checkbox', ['checkbox','dropdown']) ? ($data['style'] ?? 'checkbox') : 'checkbox';

    // Facet fields: the block's picks (validated against schema) or every facetable field.
    $facetFields = [];
    if ($isCross) {
        $facetFields[] = ['key' => '_type', 'label' => trim((string) ($data['typeLabel'] ?? '')) ?: 'Type', 'type' => 'select', 'options' => []];
    } elseif ($collection) {
        // Category-tree facet (synthetic '__cat', matches the index): filter by
        // the actual category node, localized to the page language.
        if (!empty($data['showCategoryTree'])) {
            $__pl = $__locale ?? null;
            $catOpts = \App\Models\CollectionCategoryNode::where('collection_id', $collection->id)
                ->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn ($n) => RecordDisplay::nodeName($n, $__pl))
                ->filter()->unique()->values()->all();
            if ($catOpts !== []) {
                $catLabel = trim((string) ($data['categoryLabel'] ?? '')) ?: ($__pl === 'en' ? 'Category' : 'Категория');
                $facetFields[] = ['key' => '__cat', 'label' => $catLabel, 'type' => 'select', 'options' => $catOpts];
            }
        }
        $picks = array_filter((array) ($data['fields'] ?? []), 'is_string');
        foreach ($collection->fields() as $field) {
            if (($field['facetable'] ?? false) && ($picks === [] || in_array($field['key'], $picks, true))) {
                $facetFields[] = $field;
            }
        }
    }
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="facet-filter-block cs-island {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}"
     data-cs-role="facets" data-cs-style="{{ $style }}"
     @if($csKey) data-cs-collection="{{ $csKey }}" data-cs-source="{{ $source }}" data-cs-mode="{{ $csMode }}" @endif
     @if($__htmlId) id="{{ $__htmlId }}" @endif>
@if(!$csKey)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a collection for this filter.</p>
@elseif($facetFields === [])
    <p style="opacity:.5;font-size:.85rem;">No facetable fields — mark select/boolean/relation fields as facets in the collection schema.</p>
@else
    <div style="display:flex;flex-direction:column;gap:1.75rem;padding:1.4rem 1.5rem;background:var(--color-surface,#fff);border:1px solid var(--color-border-light,rgba(26,32,44,.1));border-radius:18px;box-shadow:0 6px 22px -16px rgba(0,0,0,.25);">
    @foreach($facetFields as $field)
        <fieldset class="cs-facet" data-cs-facet="{{ $field['key'] }}" data-cs-facet-type="{{ $field['type'] }}"
                  style="border:0;padding:0;margin:0;">
            <legend style="font-weight:700;font-size:1.1rem;margin:0 0 .8rem;padding:0 0 .55rem;width:100%;border-bottom:2px solid var(--color-primary,#3b82f6);color:var(--color-heading,#1a202c);">{{ $field['label'] }}</legend>
            <div class="cs-facet-options" style="display:flex;flex-direction:column;gap:.6rem;font-size:1.02rem;">
                {{-- Options with known values render statically (visible pre-JS);
                     boolean/relation values are filled by the island from the index. --}}
                @foreach(($field['options'] ?? []) as $option)
                    <label style="display:flex;align-items:center;gap:.65rem;cursor:pointer;line-height:1.4;padding:.1rem 0;transition:color .15s ease;"
                           onmouseover="this.style.color='var(--color-primary,#3b82f6)'" onmouseout="this.style.color=''">
                        <input type="checkbox" value="{{ $option }}" data-cs-facet-value style="width:19px;height:19px;flex-shrink:0;accent-color:var(--color-primary,#3b82f6);cursor:pointer;">
                        <span style="flex:1;">{{ $option }}</span>
                        <span class="cs-count" style="opacity:.5;font-size:.88rem;"></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
    </div>
@endif
</div>
