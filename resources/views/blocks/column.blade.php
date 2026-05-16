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
<div class="column-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $padding = BlockStyle::safeDim($data['padding'] ?? '');
    $validAligns = ['start', 'center', 'end', 'stretch'];
    $vAlign = in_array($data['vertical_align'] ?? 'start', $validAligns) ? $data['vertical_align'] : 'start';
    $bgColor = BlockStyle::safeColor($data['background_color'] ?? '');

    $style = "display:flex;flex-direction:column;";
    if ($vAlign === 'center') $style .= "justify-content:center;";
    elseif ($vAlign === 'end') $style .= "justify-content:flex-end;";
    elseif ($vAlign === 'stretch') $style .= "justify-content:stretch;";
    else $style .= "justify-content:flex-start;";
    if ($padding) $style .= "padding:{$padding};";
    if ($bgColor) $style .= "background-color:{$bgColor};";
@endphp
<div style="{{ $style }}">
    {!! $children !!}
</div>

</div>
