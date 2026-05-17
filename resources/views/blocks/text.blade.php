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
<div class="text-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';
    $textAlign = in_array($data['textAlign'] ?? '', ['left','center','right','justify']) ? $data['textAlign'] : '';
    $textColor = $cssVal($data['textColor'] ?? '');
    $fontSize = $cssDim($data['fontSize'] ?? '');
    $fontWeight = in_array((string)($data['fontWeight'] ?? ''), ['300','400','500','600','700']) ? $data['fontWeight'] : '';
    $fontStyle = ($data['fontStyle'] ?? '') === 'italic' ? 'italic' : '';
    $lineHeight = $cssVal($data['lineHeight'] ?? '');
    $letterSpacing = $cssVal($data['letterSpacing'] ?? '');
@endphp
<div class="prose max-w-none" style="{{ $textAlign ? "text-align:{$textAlign};" : '' }}{{ $textColor ? "color:{$textColor};" : '' }}{{ $fontSize ? "font-size:{$fontSize};" : '' }}{{ $fontWeight ? "font-weight:{$fontWeight};" : '' }}{{ $fontStyle ? "font-style:{$fontStyle};" : '' }}{{ $lineHeight ? "line-height:{$lineHeight};" : '' }}{{ $letterSpacing ? "letter-spacing:{$letterSpacing};" : '' }}">
    {!! $data['content'] ?? '' !!}
</div>

</div>