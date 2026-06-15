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
<div class="authorbox-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $showAvatar = $data['showAvatar'] ?? true;
    $showBio = $data['showBio'] ?? true;
    $showSocialLinks = $data['showSocialLinks'] ?? false;
    $layout = $data['layout'] ?? 'horizontal';
    $isVertical = $layout === 'vertical';
    // $author would be populated at build time
    $author = $author ?? [];
@endphp
<div style="border:1px solid var(--color-border,#e2e8f0);border-radius:var(--border-radius-md,0.5rem);padding:1.5rem;{{ $isVertical ? 'text-align:center;' : 'display:flex;align-items:flex-start;gap:1rem;' }}">
    @if($showAvatar)
        @if(!empty($author['avatar']))
            <img class="img-filtered" src="{{ $author['avatar'] }}" alt="" loading="lazy" style="width:{{ $isVertical ? '64px' : '56px' }};height:{{ $isVertical ? '64px' : '56px' }};border-radius:50%;object-fit:cover;{{ $isVertical ? 'margin:0 auto 0.5rem;' : '' }}{{ $__imageFilter }}" />
        @else
            <div style="width:{{ $isVertical ? '64px' : '56px' }};height:{{ $isVertical ? '64px' : '56px' }};border-radius:50%;background:#e5e7eb;{{ $isVertical ? 'margin:0 auto 0.5rem;' : '' }}"></div>
        @endif
    @endif
    <div>
        <div style="font-weight:600;">{{ $author['name'] ?? 'Author' }}</div>
        @if($showBio)
            <p style="color:var(--color-text-muted,#6b7280);font-size:0.875rem;margin-top:0.25rem;">{{ $author['bio'] ?? '' }}</p>
        @endif
        @if($showSocialLinks && !empty($author['social']))
            <div style="display:flex;gap:0.75rem;margin-top:0.5rem;{{ $isVertical ? 'justify-content:center;' : '' }}">
                @foreach($author['social'] as $platform => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" style="color:var(--color-primary,#3b82f6);font-size:0.875rem;text-decoration:none;">{{ ucfirst($platform) }}</a>
                @endforeach
            </div>
        @endif
    </div>
</div>

</div>