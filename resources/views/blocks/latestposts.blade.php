@php
    $limit = $data['limit'] ?? 5;
    $columns = $data['columns'] ?? 1;
    $layout = $data['layout'] ?? 'cards';
    $orderBy = $data['orderBy'] ?? 'latest';
    $categoryId = $data['categoryId'] ?? '';
    $showImage = $data['showImage'] ?? true;
    $showContent = $data['showContent'] ?? false;
    $showExcerpt = $data['showExcerpt'] ?? true;
    $excerptLength = $data['excerptLength'] ?? 120;
    $showDate = $data['showDate'] ?? true;
    $showCategory = $data['showCategory'] ?? true;

    // Query posts
    $query = \App\Models\Post::where('site_id', $site->id)->where('status', 'published');
    if ($categoryId) {
        $query->where('category_id', $categoryId);
    }
    if ($showContent) {
        $query->with(['blocks' => fn($q) => $q->whereNull('parent_block_id')->orderBy('order')]);
    }
    switch ($orderBy) {
        case 'oldest': $query->orderBy('published_at', 'asc'); break;
        case 'title': $query->orderBy('title', 'asc'); break;
        case 'random': $query->inRandomOrder(); break;
        default: $query->orderByDesc('published_at');
    }
    $posts = $query->limit($limit)->get();

    // Helper: render post content from blocks
    $renderContent = function($post) use ($site) {
        $buildService = app(\App\Domain\Publishing\Services\BuildPageService::class);
        $html = '';
        foreach ($post->blocks as $block) {
            $html .= $buildService->renderBlock($block, $site);
        }
        return $html;
    };

    // Helper: get excerpt text
    $getExcerpt = function($post) use ($excerptLength) {
        $text = $post->excerpt ?: '';
        if (!$text) return '';
        if ($excerptLength > 0) return \Illuminate\Support\Str::limit($text, $excerptLength);
        return $text;
    };
@endphp
@if($posts->isEmpty())
    <div style="padding:2rem;text-align:center;color:#9ca3af;font-size:0.875rem;border:1px dashed #e5e7eb;border-radius:0.5rem;">
        No posts found{{ $categoryId ? ' in this category' : '' }}.
    </div>
@elseif($layout === 'compact')
    <ul style="list-style:none;padding:0;margin:0;">
        @foreach($posts as $post)
            <li style="padding:0.5rem 0;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:0.5rem;">
                <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text, #1e293b);text-decoration:none;font-size:0.875rem;flex:1;">{{ $post->title }}</a>
                @if($showDate)
                    <span style="font-size:0.75rem;color:#9ca3af;">{{ $post->published_at?->format('M j') }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@elseif($layout === 'list')
    <div>
        @foreach($posts as $post)
            <div style="display:flex;align-items:flex-start;gap:1rem;padding:1rem 0;border-bottom:1px solid #f3f4f6;">
                @if($showImage && $post->featured_image)
                    <img src="{{ $post->featured_image }}" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:0.5rem;flex-shrink:0;" />
                @endif
                <div style="flex:1;">
                    @if($showCategory && $post->category)
                        <span style="font-size:0.7rem;color:var(--color-primary, #3b82f6);font-weight:500;">{{ $post->category->name }}</span>
                    @endif
                    <h3 style="margin:0.25rem 0;font-weight:600;font-size:1rem;">
                        <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text, #1e293b);text-decoration:none;">{{ $post->title }}</a>
                    </h3>
                    @if($showContent)
                        <div style="margin:0.5rem 0 0;font-size:0.875rem;line-height:1.6;">{!! $renderContent($post) !!}</div>
                    @elseif($showExcerpt && $getExcerpt($post))
                        <p style="margin:0.25rem 0 0;color:#6b7280;font-size:0.8125rem;line-height:1.4;">{{ $getExcerpt($post) }}</p>
                    @endif
                    @if($showDate)
                        <span style="font-size:0.7rem;color:#9ca3af;">{{ $post->published_at?->format('M j, Y') }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    {{-- Cards / Featured --}}
    @php $isFeatured = $layout === 'featured' && $posts->count() > 1; @endphp
    @if($isFeatured)
        @php $first = $posts->shift(); @endphp
        <article style="margin-bottom:1.5rem;border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;">
            @if($showImage && $first->featured_image)
                <img src="{{ $first->featured_image }}" alt="" style="width:100%;height:280px;object-fit:cover;" />
            @endif
            <div style="padding:1.25rem;">
                @if($showCategory && $first->category)
                    <span style="font-size:0.7rem;color:var(--color-primary, #3b82f6);font-weight:500;">{{ $first->category->name }}</span>
                @endif
                <h2 style="margin:0.25rem 0;font-weight:700;font-size:1.5rem;">
                    <a href="/{{ $first->category?->slug ?? 'uncategorized' }}/{{ $first->slug }}" style="color:var(--color-text, #1e293b);text-decoration:none;">{{ $first->title }}</a>
                </h2>
                @if($showContent)
                    <div style="margin-top:0.75rem;font-size:0.9375rem;line-height:1.7;">{!! $renderContent($first) !!}</div>
                @elseif($showExcerpt && $getExcerpt($first))
                    <p style="color:#6b7280;font-size:0.875rem;margin-top:0.5rem;">{{ $getExcerpt($first) }}</p>
                @endif
                @if($showDate)
                    <span style="font-size:0.75rem;color:#9ca3af;">{{ $first->published_at?->format('M j, Y') }}</span>
                @endif
            </div>
        </article>
    @endif
    <div style="display:grid;grid-template-columns:repeat({{ $columns }}, 1fr);gap:1.5rem;">
        @foreach($posts as $post)
            <article style="border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;">
                @if($showImage && $post->featured_image)
                    <img src="{{ $post->featured_image }}" alt="" style="width:100%;height:160px;object-fit:cover;" />
                @elseif($showImage)
                    <div style="background:#f3f4f6;height:160px;"></div>
                @endif
                <div style="padding:1rem;">
                    @if($showCategory && $post->category)
                        <span style="font-size:0.7rem;color:var(--color-primary, #3b82f6);font-weight:500;">{{ $post->category->name }}</span>
                    @endif
                    <h3 style="margin:0.25rem 0;font-weight:600;">
                        <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text, #1e293b);text-decoration:none;">{{ $post->title }}</a>
                    </h3>
                    @if($showContent)
                        <div style="margin-top:0.5rem;font-size:0.8125rem;line-height:1.5;">{!! $renderContent($post) !!}</div>
                    @elseif($showExcerpt && $getExcerpt($post))
                        <p style="color:#6b7280;font-size:0.8125rem;margin-top:0.25rem;">{{ $getExcerpt($post) }}</p>
                    @endif
                    @if($showDate)
                        <span style="font-size:0.7rem;color:#9ca3af;">{{ $post->published_at?->format('M j, Y') }}</span>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif
