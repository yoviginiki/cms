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
<div class="post-loop-block {{ $__effectScope }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $layout = $data['layout'] ?? 'cards';
    $columns = (int) ($data['columns'] ?? 3);
    $limit = (int) ($data['limit'] ?? 12);
    $showImage = $data['showImage'] ?? true;
    $showExcerpt = $data['showExcerpt'] ?? true;
    $showDate = $data['showDate'] ?? true;
    $showAuthor = $data['showAuthor'] ?? false;
    $showCategory = $data['showCategory'] ?? false;
    $imageAspectRatio = $data['imageAspectRatio'] ?? '16:9';
    $excerptLines = (int) ($data['excerptLines'] ?? 3);
    $gap = $cssDim($data['gap'] ?? '') ?: '1.5rem';

    // Dynamic context: posts from archive
    $posts = $__archivePosts ?? collect();
    if ($limit > 0) $posts = $posts->take($limit);
@endphp
@if($layout === 'list')
<div style="display:flex;flex-direction:column;gap:{{ $gap }};">
@else
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:{{ $gap }};">
@endif
    @foreach($posts as $post)
    @php $postUrl = $post->url_path ?? '#'; @endphp
    @if($layout === 'list')
    <article style="display:flex;gap:1rem;padding-bottom:1rem;border-bottom:1px solid var(--color-border-light,#eee);">
        @if($showImage && $post->featured_image)
        <a href="{{ $postUrl }}" style="flex-shrink:0;"><img class="img-filtered" src="{{ $post->featured_image }}" alt="{{ e($post->title) }}" loading="lazy" style="width:200px;height:auto;object-fit:cover;aspect-ratio:{{ str_replace(':', '/', $imageAspectRatio) }};border-radius:var(--border-radius-sm,3px);{{ $__imageFilter }}" /></a>
        @endif
        <div>
            <h3 style="margin:0 0 0.25rem;"><a href="{{ $postUrl }}" style="color:var(--color-text,inherit);text-decoration:none;">{{ $post->title }}</a></h3>
            @if($showDate || $showAuthor || $showCategory)
            <div style="font-size:0.8125rem;color:var(--color-text-muted,#999);margin-bottom:0.5rem;">
                @if($showDate && $post->published_at){{ $post->published_at->format('M j, Y') }}@endif
                @if($showAuthor && $post->author) · {{ $post->author->name ?? '' }}@endif
                @if($showCategory && $post->category) · {{ $post->category->name }}@endif
            </div>
            @endif
            @if($showExcerpt && $post->excerpt)
            <p style="color:var(--color-text-muted,#666);font-size:0.875rem;{{ $excerptLines > 0 ? "display:-webkit-box;-webkit-line-clamp:{$excerptLines};-webkit-box-orient:vertical;overflow:hidden;" : '' }}">{{ $post->excerpt }}</p>
            @endif
        </div>
    </article>
    @else
    <article style="border:1px solid var(--color-border-light,#eee);border-radius:var(--border-radius-sm,3px);overflow:hidden;">
        @if($showImage && $post->featured_image)
        <a href="{{ $postUrl }}"><img class="img-filtered" src="{{ $post->featured_image }}" alt="{{ e($post->title) }}" loading="lazy" style="width:100%;aspect-ratio:{{ str_replace(':', '/', $imageAspectRatio) }};object-fit:cover;{{ $__imageFilter }}" /></a>
        @endif
        <div style="padding:1rem;">
            <h3 style="margin:0 0 0.25rem;font-size:1rem;"><a href="{{ $postUrl }}" style="color:var(--color-text,inherit);text-decoration:none;">{{ $post->title }}</a></h3>
            @if($showDate || $showAuthor || $showCategory)
            <div style="font-size:0.75rem;color:var(--color-text-muted,#999);margin-bottom:0.5rem;">
                @if($showDate && $post->published_at){{ $post->published_at->format('M j, Y') }}@endif
                @if($showAuthor && $post->author) · {{ $post->author->name ?? '' }}@endif
                @if($showCategory && $post->category) · {{ $post->category->name }}@endif
            </div>
            @endif
            @if($showExcerpt && $post->excerpt)
            <p style="color:var(--color-text-muted,#666);font-size:0.875rem;margin:0;{{ $excerptLines > 0 ? "display:-webkit-box;-webkit-line-clamp:{$excerptLines};-webkit-box-orient:vertical;overflow:hidden;" : '' }}">{{ $post->excerpt }}</p>
            @endif
        </div>
    </article>
    @endif
    @endforeach
</div>

</div>
