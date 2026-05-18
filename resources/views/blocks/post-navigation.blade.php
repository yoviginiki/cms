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
<div class="post-navigation-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $showLabels = $data['showLabels'] ?? true;
    $navStyle = $data['style'] ?? 'minimal';

    // Dynamic: pull from template context
    $prevPost = $__prevPost ?? null;
    $nextPost = $__nextPost ?? null;
@endphp
<nav class="post-nav post-nav--{{ $navStyle }}" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.5rem 0;border-top:1px solid var(--color-border,#ddd);margin-top:2rem;">
    @if($prevPost)
        <a href="{{ $prevPost->url_path }}" style="text-decoration:none;color:var(--color-text,inherit);{{ $navStyle === 'buttons' ? 'padding:0.5rem 1rem;border:1px solid var(--color-border,#ddd);border-radius:var(--border-radius-sm,3px);' : '' }}">
            <span style="font-size:0.75rem;color:var(--color-text-muted,#999);">@if($showLabels)&larr; Previous @endif</span>
            <span style="display:block;font-weight:500;">{{ $prevPost->title }}</span>
        </a>
    @else
        <span></span>
    @endif
    @if($nextPost)
        <a href="{{ $nextPost->url_path }}" style="text-decoration:none;color:var(--color-text,inherit);text-align:right;{{ $navStyle === 'buttons' ? 'padding:0.5rem 1rem;border:1px solid var(--color-border,#ddd);border-radius:var(--border-radius-sm,3px);' : '' }}">
            <span style="font-size:0.75rem;color:var(--color-text-muted,#999);">@if($showLabels)Next &rarr;@endif</span>
            <span style="display:block;font-weight:500;">{{ $nextPost->title }}</span>
        </a>
    @endif
</nav>

</div>
