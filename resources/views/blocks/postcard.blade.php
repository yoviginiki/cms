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
    $__overlayHtml = BlockEffects::overlayHtml($data ?? []);
    $__effectScope = $__effectsEnabled ? 'bfx-' . substr(md5($__htmlId ?: uniqid('', true)), 0, 8) : '';
    $__hoverCss = $__effectScope ? BlockEffects::cardHoverCss($data ?? [], $__effectScope) : '';
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
@if($__hoverCss || $__revealImgCss)<style>{{ $__hoverCss }}{{ $__revealImgCss }}</style>@endif
<div class="postcard-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $postId = $data['postId'] ?? '';
    $style = $data['style'] ?? 'vertical';
    $showExcerpt = $data['showExcerpt'] ?? true;
    $showDate = $data['showDate'] ?? true;
    $showCategory = $data['showCategory'] ?? true;
    $isHorizontal = $style === 'horizontal';
    // Post data would be populated at build time
    $post = $post ?? null;
@endphp
<article style="border:1px solid var(--color-border,#e2e8f0);border-radius:0.75rem;overflow:hidden;{{ $isHorizontal ? 'display:flex;' : '' }}">
    <div style="background:#f3f4f6;{{ $isHorizontal ? 'width:33%;min-height:120px;' : 'height:200px;' }}">
        @if($post && !empty($post['image']))
            <img class="img-filtered" src="{{ $post['image'] }}" alt="" style="width:100%;height:100%;object-fit:cover;{{ $__imageFilter }}" />
        @endif
    </div>
    <div style="padding:1.25rem;{{ $isHorizontal ? 'flex:1;' : '' }}">
        @if($showCategory)
            <div style="font-size:0.75rem;color:#3b82f6;font-weight:500;margin-bottom:0.25rem;">{{ $post['category'] ?? 'Category' }}</div>
        @endif
        <h3 style="font-weight:600;margin-bottom:0.25rem;">{{ $post['title'] ?? 'Post Title' }}</h3>
        @if($showDate)
            <div style="font-size:0.75rem;color:var(--color-text-muted,#9ca3af);margin-bottom:0.5rem;">{{ $post['date'] ?? '' }}</div>
        @endif
        @if($showExcerpt)
            <p style="color:#6b7280;font-size:0.875rem;">{{ $post['excerpt'] ?? '' }}</p>
        @endif
    </div>
</article>

</div>