@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba);
    $__customClass = BlockStyle::safeClass($__adv['customClass'] ?? '');
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="stats-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $items = $data['items'] ?? [];
    $columns = $data['columns'] ?? 3;
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:1.5rem;text-align:center;">
    @foreach($items as $item)
        <div style="padding:1.5rem;">
            <div style="font-size:2.5rem;font-weight:700;line-height:1;">{{ $item['prefix'] ?? '' }}{{ $item['value'] ?? '' }}{{ $item['suffix'] ?? '' }}</div>
            <div style="color:#6b7280;font-size:0.875rem;margin-top:0.5rem;">{{ $item['label'] ?? '' }}</div>
        </div>
    @endforeach
</div>

</div>