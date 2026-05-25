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

    // Card border
    $cardBorder = $data['cardBorder'] ?? true;
    $cardBorderWidth = max(0, min(8, intval($data['cardBorderWidth'] ?? 1)));
    $cardBorderColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', $data['cardBorderColor'] ?? '') ? $data['cardBorderColor'] : '#e5e7eb';
    $cardBorderStyle = in_array($data['cardBorderStyle'] ?? 'solid', ['solid','dashed','dotted','double','none']) ? ($data['cardBorderStyle'] ?? 'solid') : 'solid';
    $cardBorderRadius = max(0, min(32, intval($data['cardBorderRadius'] ?? 12)));
    $shadowMap = ['none' => 'none', 'sm' => '0 1px 2px rgba(0,0,0,0.05)', 'md' => '0 4px 6px rgba(0,0,0,0.07)', 'lg' => '0 10px 15px rgba(0,0,0,0.1)', 'xl' => '0 20px 25px rgba(0,0,0,0.15)'];
    $cardShadow = $shadowMap[$data['cardShadow'] ?? 'none'] ?? 'none';
    $cardBg = preg_match('/^(#[0-9a-fA-F]{3,8}|transparent|inherit)$/', $data['cardBg'] ?? '') ? $data['cardBg'] : '';
    $cardPadding = preg_match('/^[\d\s.]+(?:px|rem|em|%)?(?:\s+[\d.]+(?:px|rem|em|%)?){0,3}$/', $data['cardPadding'] ?? '') ? $data['cardPadding'] : '0';

    // Heading
    $showHeading = $data['showHeading'] ?? true;
    $headingPosition = in_array($data['headingPosition'] ?? 'below', ['above','below','vertical-left','vertical-right']) ? ($data['headingPosition'] ?? 'below') : 'below';
    $isVerticalHeading = in_array($headingPosition, ['vertical-left', 'vertical-right']);
    $headingVerticalDir = in_array($data['headingVerticalDir'] ?? 'up', ['up','down','away']) ? ($data['headingVerticalDir'] ?? 'up') : 'up';
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
    $excerptLength = max(0, min(1000, intval($data['excerptLength'] ?? 120)));
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

    // Card effects
    $__effectsEnabled = BlockEffects::isEnabled($data);
    $__cardBaseStyle = BlockEffects::cardBaseStyle($data);
    $__imageFilter = BlockEffects::imageFilterStyle($data);
    $__overlayHtml = BlockEffects::overlayHtml($data);
    $__effectScope = $__effectsEnabled ? 'pgfx-' . substr(md5($__htmlId ?: uniqid('', true)), 0, 8) : '';
    $__hoverCss = $__effectScope ? BlockEffects::cardHoverCss($data, $__effectScope) : '';
    $__revealEnabled = BlockEffects::isRevealEnabled($data);
    // Simple approach: transition filter to none on hover
    $__revealDuration = max(150, min(1500, intval($data['effects']['imageHoverReveal']['duration'] ?? 500)));
    $__revealEasing = in_array($data['effects']['imageHoverReveal']['easing'] ?? 'ease-out', ['ease','ease-out','ease-in-out']) ? ($data['effects']['imageHoverReveal']['easing'] ?? 'ease-out') : 'ease-out';
    $__revealImgCss = $__revealEnabled && $__effectScope
        ? ".{$__effectScope}:hover .img-filtered{filter:none!important}"
          . ".{$__effectScope} .img-filtered{transition:filter {$__revealDuration}ms {$__revealEasing}}"
          . "@media(prefers-reduced-motion:reduce){.{$__effectScope} .img-filtered{transition:none!important}}"
        : '';
@endphp
@if($__hoverCss || $__revealImgCss)<style>{{ $__hoverCss }}{{ $__revealImgCss }}</style>@endif
@if($posts->isEmpty())
    <div style="padding:2rem;text-align:center;color:var(--color-text-muted,#9ca3af);font-size:0.875rem;border:1px dashed #e5e7eb;border-radius:0.5rem;">
        No posts found{{ $categoryId ? ' in this category' : '' }}.
    </div>
@else
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:{{ $gap }};">
    @foreach($posts as $post)
        <article class="{{ $__effectScope }}" style="{{ $cardBorder ? 'border:' . $cardBorderWidth . 'px ' . $cardBorderStyle . ' ' . $cardBorderColor . ';' : 'border:none;' }}border-radius:{{ $cardBorderRadius }}px;overflow:{{ $__effectsEnabled ? 'visible' : 'hidden' }};box-shadow:{{ $cardShadow }};{{ $cardBg ? 'background-color:' . $cardBg . ';' : '' }}{{ $cardPadding !== '0' ? 'padding:' . $cardPadding . ';' : '' }}{{ $__cardBaseStyle }}{{ $isHorizontal ? 'display:flex;' : '' }}{{ $isVerticalHeading ? 'display:flex;' . ($headingPosition === 'vertical-right' ? 'flex-direction:row;' : 'flex-direction:row-reverse;') : '' }}">
            {{-- Heading ABOVE image --}}
            @if($showHeading && $headingPosition === 'above')
            <div style="padding:0.75rem 1rem 0.25rem;">
                <{{ $headingTag }} style="font-weight:600;font-size:{{ $headingSizePx }}px;font-family:{{ $headingFont }};text-align:{{ $headingAlign }};padding:{{ $headingPadding }};margin:{{ $headingMargin }};">
                    <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text,#1e293b);text-decoration:none;">{{ $post->title }}</a>
                </{{ $headingTag }}>
            </div>
            @endif
            {{-- Vertical heading LEFT --}}
            @if($showHeading && $headingPosition === 'vertical-left')
            @php
                $vlTransform = $headingVerticalDir === 'up' ? 'transform:rotate(180deg);' : ($headingVerticalDir === 'away' ? 'transform:rotate(180deg);' : '');
            @endphp
            <div style="writing-mode:vertical-rl;text-orientation:mixed;padding:0.5rem 0.25rem;display:flex;align-items:center;justify-content:center;min-width:{{ $headingSizePx + 8 }}px;{{ $vlTransform }}">
                <{{ $headingTag }} style="font-weight:600;font-size:{{ $headingSizePx }}px;font-family:{{ $headingFont }};margin:0;white-space:nowrap;">
                    <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text,#1e293b);text-decoration:none;">{{ $post->title }}</a>
                </{{ $headingTag }}>
            </div>
            @endif
            @if($showImage)
            <div style="background:#f3f4f6;position:relative;overflow:hidden;{{ $isVerticalHeading ? 'flex:1;height:' . $imageHeight . ';' : ($isHorizontal ? 'width:33%;height:' . $imageHeight . ';' : 'width:' . $imageWidth . ';height:' . $imageHeight . ';') }}{{ $imageWidth !== '100%' && !$isHorizontal && !$isVerticalHeading ? 'margin:0 auto;' : '' }}">
                @if($post->featured_image)
                    <img src="{{ $post->featured_image }}" alt="{{ $post->title ?? '' }}" class="{{ $__revealEnabled ? 'img-filtered' : '' }}" style="width:100%;height:100%;object-fit:cover;{{ $__imageFilter }}" />
                @endif
                {!! $__overlayHtml !!}
            </div>
            @endif
            {{-- Vertical heading RIGHT --}}
            @if($showHeading && $headingPosition === 'vertical-right')
            @php
                $vrTransform = $headingVerticalDir === 'up' ? 'transform:rotate(180deg);' : ($headingVerticalDir === 'away' ? '' : '');
            @endphp
            <div style="writing-mode:vertical-rl;text-orientation:mixed;padding:0.5rem 0.25rem;display:flex;align-items:center;justify-content:center;min-width:{{ $headingSizePx + 8 }}px;{{ $vrTransform }}">
                <{{ $headingTag }} style="font-weight:600;font-size:{{ $headingSizePx }}px;font-family:{{ $headingFont }};margin:0;white-space:nowrap;">
                    <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text,#1e293b);text-decoration:none;">{{ $post->title }}</a>
                </{{ $headingTag }}>
            </div>
            @endif
            {{-- Content below (heading below + excerpt) --}}
            @if(($showHeading && $headingPosition === 'below') || ($showExcerpt && $post->excerpt))
            <div style="padding:1rem;{{ $isHorizontal ? 'flex:1;' : '' }}">
                @if($showHeading && $headingPosition === 'below')
                <{{ $headingTag }} style="font-weight:600;font-size:{{ $headingSizePx }}px;font-family:{{ $headingFont }};text-align:{{ $headingAlign }};padding:{{ $headingPadding }};margin:{{ $headingMargin }};">
                    <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text,#1e293b);text-decoration:none;">{{ $post->title }}</a>
                </{{ $headingTag }}>
                @endif
                @if($showExcerpt && $post->excerpt)
                    <p style="color:#6b7280;font-size:{{ $excerptSizePx }}px;font-family:{{ $excerptFont }};text-align:{{ $excerptAlign }};padding:{{ $excerptPadding }};margin:{{ $excerptMargin }};">{{ $excerptLength > 0 ? \Illuminate\Support\Str::limit($post->excerpt, $excerptLength) : $post->excerpt }}</p>
                @endif
            </div>
            @endif
        </article>
    @endforeach
</div>
@endif

</div>
