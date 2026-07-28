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
<div class="button-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $style = $data['style'] ?? 'primary';
    $size = $data['size'] ?? 'md';
    $target = ($data['target'] ?? '_self') !== '_self' ? ' target="' . e($data['target']) . '" rel="noopener"' : '';
    $sizeClass = match($size) { 'sm' => 'btn-sm', 'lg' => 'btn-lg', default => '' };
    $styleClass = match($style) { 'secondary' => 'btn-secondary', 'outline' => 'btn-outline', 'ghost' => 'btn-ghost', default => 'btn-primary' };
    $safeUrl = fn($v) => preg_match('/^(javascript|data|vbscript)\s*:/i', preg_replace('/[\x00-\x1f\x7f\s]/', '', (string) $v)) ? '#' : (string) $v;
    // Optional per-button skin (migrated CTAs keep their source colours/size).
    $bg = \App\Support\Blocks\BlockStyle::safeColor($data['bgColor'] ?? '');
    $fg = \App\Support\Blocks\BlockStyle::safeColor($data['textColor'] ?? '');
    $fs = \App\Support\Blocks\BlockStyle::safeDim($data['fontSize'] ?? '');
    $fw = in_array((string)($data['fontWeight'] ?? ''), ['400','500','600','700','800','900']) ? $data['fontWeight'] : '';
    $skin = ($bg ? "background:{$bg};border-color:{$bg};" : '') . ($fg ? "color:{$fg};" : '') . ($fs ? "font-size:{$fs};" : '') . ($fw ? "font-weight:{$fw};" : '');
@endphp
<a href="{{ e($safeUrl($data['url'] ?? '#')) }}" class="btn {{ $styleClass }} {{ $sizeClass }}"@if($skin) style="{{ $skin }}"@endif{!! $target !!}>{{ $data['text'] ?? 'Button' }}</a>

</div>