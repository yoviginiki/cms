@use('App\Support\Blocks\BlockStyle')
@use('App\Support\Blocks\LinkIcons')
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
    $lkHref = LinkIcons::safeHref((string) ($data['linkUrl'] ?? ''));
    $lkText = trim((string) ($data['text'] ?? '')) ?: ($lkHref ?? '');
    $lkIcon = LinkIcons::resolve((string) ($data['icon'] ?? 'auto'), (string) ($lkHref ?? ''));
    $lkRight = ($data['iconPosition'] ?? 'left') === 'right';
    $lkBlank = ($data['linkTarget'] ?? '_self') === '_blank';
    $lkColor = BlockStyle::safeColor($data['color'] ?? '') ?: 'var(--color-link,var(--color-primary,#3b82f6))';
    $lkSize = preg_match('/^\d+(\.\d+)?(px|rem|em)$/', (string) ($data['fontSize'] ?? '')) ? $data['fontSize'] : '';
    $lkWeight = in_array((string) ($data['fontWeight'] ?? ''), ['400', '500', '600', '700', '800'], true) ? $data['fontWeight'] : '';
    $lkUnderline = in_array($data['underline'] ?? 'hover', ['none', 'hover', 'always'], true) ? ($data['underline'] ?? 'hover') : 'hover';
    $lkAlign = in_array($data['align'] ?? 'left', ['left', 'center', 'right'], true) ? ($data['align'] ?? 'left') : 'left';
    $lkIconColor = !empty($data['brandColor']) && $lkIcon ? (LinkIcons::BRAND[$lkIcon] ?? null) : null;
    $lkSvg = $lkIcon ? LinkIcons::svg($lkIcon) : '';
    if ($lkSvg && $lkIconColor) { $lkSvg = str_replace('stroke="currentColor"', 'stroke="' . $lkIconColor . '"', $lkSvg); }
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="link-block link-block--u-{{ $lkUnderline }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;text-align:{{ $lkAlign }};{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
<style>.link-block a{display:inline-flex;align-items:center;gap:.45em;text-decoration:none}.link-block--u-always a span,.link-block--u-hover a:hover span{text-decoration:underline;text-underline-offset:.2em}</style>
@if($lkHref)
    <a href="{{ $lkHref }}" @if($lkBlank) target="_blank" rel="noopener noreferrer" @endif style="color:{{ $lkColor }};@if($lkSize)font-size:{{ $lkSize }};@endif @if($lkWeight)font-weight:{{ $lkWeight }};@endif">@if(!$lkRight){!! $lkSvg !!}@endif<span>{{ $lkText }}</span>@if($lkRight){!! $lkSvg !!}@endif</a>
@endif
</div>
