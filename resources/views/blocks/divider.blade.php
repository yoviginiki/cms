@use('App\Support\Blocks\BlockStyle')
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
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="divider-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $style = $data['style'] ?? 'solid';
    $color = $data['color'] ?? '#d1d5db';
    $thickness = $data['thickness'] ?? '1px';
    $width = $data['width'] ?? '100%';
    $alignment = $data['alignment'] ?? 'center';
    $marginMap = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];
    $margin = $marginMap[$alignment] ?? '0 auto';
@endphp
<hr class="divider-block" style="border: none; border-top: {{ $thickness }} {{ $style }} {{ $color }}; width: {{ $width }}; margin: {{ $margin }};">

</div>