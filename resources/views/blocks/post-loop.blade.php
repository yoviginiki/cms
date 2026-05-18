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
<div class="post-loop-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
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
        <a href="{{ $postUrl }}" style="flex-shrink:0;"><img src="{{ $post->featured_image }}" alt="{{ e($post->title) }}" loading="lazy" style="width:200px;height:auto;object-fit:cover;aspect-ratio:{{ str_replace(':', '/', $imageAspectRatio) }};border-radius:var(--border-radius-sm,3px);" /></a>
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
        <a href="{{ $postUrl }}"><img src="{{ $post->featured_image }}" alt="{{ e($post->title) }}" loading="lazy" style="width:100%;aspect-ratio:{{ str_replace(':', '/', $imageAspectRatio) }};object-fit:cover;" /></a>
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
