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
<div class="row-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $layoutMap = [
        '1' => '1fr',
        '1/2+1/2' => '1fr 1fr',
        '1/3+2/3' => '1fr 2fr',
        '2/3+1/3' => '2fr 1fr',
        '1/3+1/3+1/3' => '1fr 1fr 1fr',
        '1/4+1/4+1/4+1/4' => '1fr 1fr 1fr 1fr',
        '1/4+3/4' => '1fr 3fr',
        '3/4+1/4' => '3fr 1fr',
    ];
    $layout = $data['layout'] ?? '1/2+1/2';
    $gridCols = $layoutMap[$layout] ?? '1fr 1fr';
    $gap = BlockStyle::safeDim($data['gap'] ?? '16px') ?: '16px';
    $maxW = BlockStyle::safeDim($data['max_width'] ?? '');
    $validAligns = ['start', 'center', 'end', 'stretch'];
    $vAlign = in_array($data['vertical_align'] ?? 'stretch', $validAligns) ? $data['vertical_align'] : 'stretch';

    $style = "display:grid;grid-template-columns:{$gridCols};gap:{$gap};align-items:{$vAlign};";
    if ($maxW) {
        $style .= "max-width:{$maxW};margin:0 auto;";
    }
@endphp
<div style="{{ $style }}">
    {!! $children !!}
</div>

</div>
