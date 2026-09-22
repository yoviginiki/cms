@use('App\Support\Blocks\BlockStyle')
@use('App\Support\Blocks\BlockEffects')
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
@php
    // Card effects
    $__effectsEnabled = BlockEffects::isEnabled($data ?? []);
    $__imageFilter = BlockEffects::imageFilterStyle($data ?? []);
    $__effectScope = $__effectsEnabled ? 'bfx-' . substr(md5($__htmlId ?: uniqid('', true)), 0, 8) : '';
    $__hoverCss = $__effectScope ? BlockEffects::cardHoverCss($data ?? [], $__effectScope) : '';
    if ($__effectScope && (($data['effects']['hover']['preset'] ?? '') === 'shine')) {
        $__hoverCss .= BlockEffects::shineCss(".{$__effectScope} .gallery-item");
    }
    $__revealEnabled = BlockEffects::isRevealEnabled($data ?? []);
    $__revealMode = in_array(($data['effects']['imageHoverReveal']['mode'] ?? 'fade'), ['none','fade','reveal-left','reveal-right','reveal-top','reveal-bottom','circle','diagonal']) ? ($data['effects']['imageHoverReveal']['mode'] ?? 'fade') : 'fade';
    $__isFadeReveal = $__revealMode === 'fade' || $__revealMode === 'none';
    $__revealDuration = max(150, min(1500, intval($data['effects']['imageHoverReveal']['duration'] ?? 500)));
    $__revealEasing = in_array($data['effects']['imageHoverReveal']['easing'] ?? 'ease-out', ['ease','ease-out','ease-in-out']) ? ($data['effects']['imageHoverReveal']['easing'] ?? 'ease-out') : 'ease-out';
    if ($__revealEnabled && $__effectScope && $__isFadeReveal) {
        $__revealImgCss = ".{$__effectScope}:hover .img-filtered{filter:none!important}.{$__effectScope} .img-filtered{transition:filter {$__revealDuration}ms {$__revealEasing}}@media(prefers-reduced-motion:reduce){.{$__effectScope} .img-filtered{transition:none!important}}";
    } elseif ($__revealEnabled && $__effectScope) {
        $__revealImgCss = BlockEffects::revealCss($data ?? [], $__effectScope);
    } else {
        $__revealImgCss = '';
    }
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
@if($__hoverCss || $__revealImgCss)<style>{!! $__hoverCss !!}{!! $__revealImgCss !!}</style>@endif
<div class="gallery-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $images = $data['images'] ?? [];
    $layout = $data['layout'] ?? 'grid';
    $columns = max(1, min(8, (int) ($data['columns'] ?? 3)));
    $gap = preg_match('/^\d+(\.\d+)?(px|rem|em|%)$/', $data['gap'] ?? '') ? $data['gap'] : '8px';
    $lightbox = ($data['lightbox'] ?? true) !== false;
    $captions = ($data['captions'] ?? false) === true;
    $ratioMap = ['square' => '1 / 1', '4:3' => '4 / 3', '3:2' => '3 / 2', '16:9' => '16 / 9', 'natural' => null];
    $aspect = array_key_exists($data['aspect'] ?? '', $ratioMap) ? $ratioMap[$data['aspect']] : '1 / 1';
    $items = [];
    foreach ($images as $img) {
        $src = is_array($img) ? ($img['src'] ?? $img['url'] ?? '') : (string) $img;
        if ($src === '') continue;
        $items[] = [
            'src' => $src,
            'alt' => is_array($img) ? (string) ($img['alt'] ?? '') : '',
            'caption' => is_array($img) ? trim((string) ($img['caption'] ?? '')) : '',
            'link' => is_array($img) ? trim((string) ($img['link'] ?? '')) : '',
            'w' => is_array($img) ? ($img['width'] ?? null) : null,
            'h' => is_array($img) ? ($img['height'] ?? null) : null,
        ];
    }
@endphp
@if($lightbox && $items)
@include('blocks.partials.lightbox')
@once('gallery-item-css')<style>.gallery-item{display:block;margin:0;min-width:0}.gallery-item a{display:block;cursor:zoom-in}.gallery-item figcaption{font-size:var(--font-size-sm,0.875rem);color:var(--color-text-muted,#666);margin-top:0.4em;text-align:center;line-height:1.4}</style>@endonce
@endif
<div class="gallery-block gallery-block--{{ $layout }}" data-lightbox="{{ $lightbox ? '1' : '0' }}" style="display:grid;grid-template-columns:repeat({{ $columns }}, 1fr);gap:{{ e($gap) }};">
    @foreach($items as $i => $it)
        @php
            $imgStyle = 'width:100%;height:auto;display:block;border-radius:4px;' . ($aspect ? "aspect-ratio:{$aspect};object-fit:cover;" : '') . $__imageFilter;
            $href = $it['link'] !== '' ? $it['link'] : $it['src'];
            $isLightbox = $lightbox && $it['link'] === '';
        @endphp
        <figure class="gallery-item" style="border-radius:4px;">
            <a href="{{ e($href) }}" @if($isLightbox) data-glb-item data-caption="{{ e($it['caption']) }}" @endif @if($it['link'] !== '' && preg_match('#^https?://#', $it['link'])) target="_blank" rel="noopener" @endif aria-label="{{ e($it['caption'] !== '' ? $it['caption'] : ($it['alt'] !== '' ? $it['alt'] : 'Image ' . ($i + 1))) }}">
            <img class="img-filtered" src="{{ e($it['src']) }}" alt="{{ e($it['alt']) }}"@if($it['w']) width="{{ (int) $it['w'] }}"@endif @if($it['h'])height="{{ (int) $it['h'] }}"@endif loading="{{ $i < 3 ? 'eager' : 'lazy' }}" decoding="async" style="{{ $imgStyle }}">
            </a>
            @if($captions && $it['caption'] !== '')
            <figcaption>{{ $it['caption'] }}</figcaption>
            @endif
        </figure>
    @endforeach
</div>

</div>