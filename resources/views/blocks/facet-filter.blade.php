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

    {{-- '*' = cross-collection search: one synthetic "Type" facet, filled by JS. --}}
    $isCross = ($data['collectionId'] ?? null) === '*';
    $collection = (!$isCross && !empty($data['collectionId'])) ? \App\Models\ContentCollection::find($data['collectionId']) : ($isCross ? null : ($__collection ?? null));
    [$csMode, $source] = $isCross ? ['static', '/search/index.json'] : ($collection ? RecordDisplay::searchSource($collection, $site) : ['static', '']);
    $csKey = $isCross ? '_site' : $collection?->slug;
    $style = in_array($data['style'] ?? 'checkbox', ['checkbox','dropdown']) ? ($data['style'] ?? 'checkbox') : 'checkbox';

    // Facet fields: the block's picks (validated against schema) or every facetable field.
    $facetFields = [];
    if ($isCross) {
        $facetFields[] = ['key' => '_type', 'label' => trim((string) ($data['typeLabel'] ?? '')) ?: 'Type', 'type' => 'select', 'options' => []];
    } elseif ($collection) {
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
    @foreach($facetFields as $field)
        <fieldset class="cs-facet" data-cs-facet="{{ $field['key'] }}" data-cs-facet-type="{{ $field['type'] }}"
                  style="border:0;padding:0;margin:0 0 1.1rem;">
            <legend style="font-weight:600;font-size:.9rem;margin-bottom:.4rem;padding:0;">{{ $field['label'] }}</legend>
            <div class="cs-facet-options" style="display:flex;flex-direction:column;gap:.25rem;font-size:.9rem;">
                {{-- Options with known values render statically (visible pre-JS);
                     boolean/relation values are filled by the island from the index. --}}
                @foreach(($field['options'] ?? []) as $option)
                    <label style="display:flex;align-items:center;gap:.45rem;cursor:pointer;">
                        <input type="checkbox" value="{{ $option }}" data-cs-facet-value>
                        <span>{{ $option }}</span>
                        <span class="cs-count" style="opacity:.5;font-size:.8rem;"></span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
@endif
</div>
