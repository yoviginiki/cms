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
<div class="record-title-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';
    $tag = in_array($data['tag'] ?? 'h1', ['h1','h2','h3','h4','h5','h6']) ? ($data['tag'] ?? 'h1') : 'h1';
    $fontSize = $cssDim($data['fontSize'] ?? '');
    $color = BlockStyle::safeColor($data['color'] ?? '');
    $textAlign = in_array($data['textAlign'] ?? '', ['left','center','right']) ? $data['textAlign'] : '';
    $record = $__record ?? null;
@endphp
<{{ $tag }} style="{{ $fontSize ? "font-size:{$fontSize};" : '' }}{{ $color ? "color:{$color};" : '' }}{{ $textAlign ? "text-align:{$textAlign};" : '' }}">{{ $record?->title ?? 'Record Title' }}</{{ $tag }}>
</div>
