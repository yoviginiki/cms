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
<div class="heading-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $level = in_array($data['level'] ?? 'h2', ['h1','h2','h3','h4','h5','h6']) ? ($data['level'] ?? 'h2') : 'h2';
    $defaultSizeMap = ['h1' => 'var(--font-size-3xl,2rem)', 'h2' => 'var(--font-size-2xl,1.5rem)', 'h3' => 'var(--font-size-xl,1.25rem)', 'h4' => 'var(--font-size-lg,1.125rem)', 'h5' => 'var(--font-size-base,1rem)', 'h6' => 'var(--font-size-sm,0.875rem)'];

    // User overrides from block data
    $fontSize = $cssDim($data['fontSize'] ?? '') ?: ($defaultSizeMap[$level] ?? $defaultSizeMap['h2']);
    $fontWeight = in_array((string)($data['fontWeight'] ?? ''), ['400','500','600','700','800','900']) ? $data['fontWeight'] : 'var(--font-weight-bold,700)';
    $color = $cssVal($data['color'] ?? '') ?: 'var(--color-text,#1e293b)';
    $lineHeight = $cssVal($data['lineHeight'] ?? '') ?: 'var(--line-height-tight,1.25)';
    $letterSpacing = $cssVal($data['letterSpacing'] ?? '');
    $textTransform = in_array($data['textTransform'] ?? '', ['uppercase','lowercase','capitalize']) ? $data['textTransform'] : '';
    $textAlign = in_array($data['textAlign'] ?? '', ['left','center','right']) ? $data['textAlign'] : '';
@endphp
<{{ $level }} style="font-size:{{ $fontSize }};font-weight:{{ $fontWeight }};font-family:var(--font-heading,inherit);line-height:{{ $lineHeight }};color:{{ $color }};@if($letterSpacing)letter-spacing:{{ $letterSpacing }};@endif @if($textTransform)text-transform:{{ $textTransform }};@endif @if($textAlign)text-align:{{ $textAlign }};@endif">{{ $data['text'] ?? '' }}</{{ $level }}>

</div>