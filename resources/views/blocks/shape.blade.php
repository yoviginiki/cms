@php
    use App\Support\Blocks\BlockStyle;
    /** Simple rectangle primitive (slider layer). Fills its .sp-layer box —
        size/position come from the layer wrapper's layout. */
    $color = BlockStyle::safeColor($data['color'] ?? '') ?: 'var(--color-primary, #E63B2E)';
@endphp
<div class="sp-shape-fill" style="background:{{ $color }}"></div>
