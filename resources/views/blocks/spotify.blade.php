@use('App\Support\Blocks\BlockStyle')
@use('App\Support\Blocks\SpotifyEmbed')
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
    $spUrl = (string) ($data['url'] ?? '');
    $spSrc = SpotifyEmbed::embedUrl($spUrl, ($data['theme'] ?? 'color') === 'dark');
    $spHeight = (int) ($data['height'] ?? 0) ?: SpotifyEmbed::defaultHeight($spUrl, (string) ($data['size'] ?? 'normal'));
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="spotify-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@if($spSrc)
    <iframe src="{{ $spSrc }}" width="100%" height="{{ $spHeight }}" style="--sp-h:{{ $spHeight }}px;height:var(--sp-h);display:block;border:0;border-radius:12px;"
            allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy" title="Spotify"></iframe>
@endif
</div>
