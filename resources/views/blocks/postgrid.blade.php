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
<div class="postgrid-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;max-width:1200px;margin-left:auto;margin-right:auto;padding:2rem 1rem;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $categoryId = $data['categoryId'] ?? '';
    $limit = $data['limit'] ?? 6;
    $columns = max(1, min(6, intval($data['columns'] ?? 3)));
    $cardStyle = $data['cardStyle'] ?? 'vertical';
    $isHorizontal = $cardStyle === 'horizontal';

    // Image
    $showImage = $data['showImage'] ?? true;
    $imageHeightPx = max(40, min(600, intval($data['imageHeight'] ?? 160)));
    $imageWidth = $data['imageWidth'] ?? '100%';
    // Sanitize imageWidth — only allow safe CSS values
    if (!preg_match('/^(auto|\d{1,3}%)$/', $imageWidth)) $imageWidth = '100%';
    $imageHeight = 'clamp(' . round($imageHeightPx * 0.4) . 'px, ' . round($imageHeightPx / 10, 1) . 'vw, ' . $imageHeightPx . 'px)';

    // Gap
    $gapPx = max(0, min(64, intval($data['gap'] ?? 24)));
    $gap = 'clamp(' . round($gapPx * 0.4) . 'px, ' . round($gapPx / 10, 1) . 'vw, ' . $gapPx . 'px)';

    // Heading
    $showHeading = $data['showHeading'] ?? true;
    $headingTag = in_array($data['headingTag'] ?? 'h3', ['h2','h3','h4']) ? ($data['headingTag'] ?? 'h3') : 'h3';
    $headingSizePx = max(10, min(48, intval($data['headingSize'] ?? 16)));
    $headingFont = $data['headingFont'] ?? 'inherit';
    // Sanitize font family — strip dangerous chars
    $headingFont = preg_replace('/[^a-zA-Z0-9\s,]/', '', $headingFont);
    $headingAlign = in_array($data['headingAlign'] ?? 'left', ['left','center','right']) ? ($data['headingAlign'] ?? 'left') : 'left';
    $headingPadding = preg_match('/^[\d\s.]+(?:px|rem|em|%)?(?:\s+[\d.]+(?:px|rem|em|%)?){0,3}$/', $data['headingPadding'] ?? '') ? $data['headingPadding'] : '0';
    $headingMargin = preg_match('/^[\d\s.]+(?:px|rem|em|%)?(?:\s+[\d.]+(?:px|rem|em|%)?){0,3}$/', $data['headingMargin'] ?? '') ? $data['headingMargin'] : '0 0 0.25rem 0';

    // Excerpt
    $showExcerpt = $data['showExcerpt'] ?? false;
    $excerptSizePx = max(10, min(32, intval($data['excerptSize'] ?? 14)));
    $excerptFont = preg_replace('/[^a-zA-Z0-9\s,]/', '', $data['excerptFont'] ?? 'inherit');
    $excerptAlign = in_array($data['excerptAlign'] ?? 'left', ['left','center','right']) ? ($data['excerptAlign'] ?? 'left') : 'left';
    $excerptPadding = preg_match('/^[\d\s.]+(?:px|rem|em|%)?(?:\s+[\d.]+(?:px|rem|em|%)?){0,3}$/', $data['excerptPadding'] ?? '') ? $data['excerptPadding'] : '0';
    $excerptMargin = preg_match('/^[\d\s.]+(?:px|rem|em|%)?(?:\s+[\d.]+(?:px|rem|em|%)?){0,3}$/', $data['excerptMargin'] ?? '') ? $data['excerptMargin'] : '0.25rem 0 0 0';

    // Query published posts from the site
    $query = \App\Models\Post::where('site_id', $site->id)->where('status', 'published')->with('category');
    if ($categoryId) {
        $query->where('category_id', $categoryId);
    }
    $posts = $query->orderByDesc('published_at')->limit($limit)->get();
@endphp
@if($posts->isEmpty())
    <div style="padding:2rem;text-align:center;color:var(--color-text-muted,#9ca3af);font-size:0.875rem;border:1px dashed #e5e7eb;border-radius:0.5rem;">
        No posts found{{ $categoryId ? ' in this category' : '' }}.
    </div>
@else
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:{{ $gap }};">
    @foreach($posts as $post)
        <article style="border:1px solid var(--color-border,#e5e7eb);border-radius:0.75rem;overflow:hidden;{{ $isHorizontal ? 'display:flex;' : '' }}">
            @if($showImage)
            <div style="background:#f3f4f6;{{ $isHorizontal ? 'width:33%;height:' . $imageHeight . ';' : 'width:' . $imageWidth . ';height:' . $imageHeight . ';' }}{{ $imageWidth !== '100%' && !$isHorizontal ? 'margin:0 auto;' : '' }}">
                @if($post->featured_image)
                    <img src="{{ $post->featured_image }}" alt="" style="width:100%;height:100%;object-fit:cover;" />
                @endif
            </div>
            @endif
            <div style="padding:1rem;{{ $isHorizontal ? 'flex:1;' : '' }}">
                @if($showHeading)
                <{{ $headingTag }} style="font-weight:600;font-size:{{ $headingSizePx }}px;font-family:{{ $headingFont }};text-align:{{ $headingAlign }};padding:{{ $headingPadding }};margin:{{ $headingMargin }};">
                    <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text,#1e293b);text-decoration:none;">{{ $post->title }}</a>
                </{{ $headingTag }}>
                @endif
                @if($showExcerpt && $post->excerpt)
                    <p style="color:#6b7280;font-size:{{ $excerptSizePx }}px;font-family:{{ $excerptFont }};text-align:{{ $excerptAlign }};padding:{{ $excerptPadding }};margin:{{ $excerptMargin }};">{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>
                @endif
            </div>
        </article>
    @endforeach
</div>
@endif

</div>
