<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog | {{ $site->name }}</title>
    <meta name="description" content="Latest posts from {{ $site->name }}">
    <link rel="canonical" href="{{ $baseUrl }}/blog">
    <meta property="og:title" content="Blog | {{ $site->name }}">
    <meta property="og:type" content="website">
    @if(!empty($rssUrl))<link rel="alternate" type="application/rss+xml" title="{{ $site->name }} Feed" href="{{ $rssUrl }}">@endif
    @if(!empty($designTokensCss))<style>{!! $designTokensCss !!}</style>@endif
    @if(!empty($criticalCss))<style>{!! $criticalCss !!}</style>@endif
    @if(!empty($customCss))<style>{!! $customCss !!}</style>@endif
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @if(!empty($headScripts)){!! $headScripts !!}@endif
    {!! $archiveJsonLd ?? '' !!}
</head>
<body>
    <header role="banner">@if(!empty($navigation)){!! $navigation !!}@endif</header>
    <main role="main" style="max-width:var(--container-width,800px);margin:0 auto;padding:var(--space-8,2rem) var(--container-padding,1rem);">
        <h1>Blog</h1>
        <div class="post-list">
        @foreach($posts as $post)
            <article style="margin-bottom:var(--space-8,2rem);padding-bottom:var(--space-8,2rem);border-bottom:1px solid var(--color-border,#e5e7eb);">
                <h2 style="font-size:var(--font-size-xl,1.25rem);margin-bottom:var(--space-2,0.5rem);"><a href="{{ $post->url_path }}">{{ $post->title }}</a></h2>
                @if($post->excerpt)<p style="color:var(--color-text-muted,#64748b);margin-bottom:var(--space-2,0.5rem);">{{ $post->excerpt }}</p>@endif
                <div style="font-size:var(--font-size-sm,0.875rem);color:var(--color-text-muted,#9ca3af);">
                    <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('M j, Y') }}</time>
                    @if($post->category) &middot; <a href="/{{ $post->category->slug }}">{{ $post->category->name }}</a>@endif
                </div>
            </article>
        @endforeach
        </div>
        @if($currentPage < $totalPages)
        <nav aria-label="Pagination" style="padding:var(--space-8,2rem) 0;display:flex;gap:var(--space-2,0.5rem);justify-content:center;">
            @for($i = 1; $i <= $totalPages; $i++)
                @if($i === $currentPage)
                    <span aria-current="page" style="padding:var(--space-2,0.5rem) var(--space-4,1rem);background:var(--color-primary,#3b82f6);color:#fff;border-radius:var(--border-radius-md,8px);">{{ $i }}</span>
                @else
                    <a href="/blog{{ $i > 1 ? '/page/' . $i : '' }}" style="padding:var(--space-2,0.5rem) var(--space-4,1rem);border:1px solid var(--color-border,#e5e7eb);border-radius:var(--border-radius-md,8px);text-decoration:none;">{{ $i }}</a>
                @endif
            @endfor
        </nav>
        @endif
    </main>
    <footer role="contentinfo">@if(!empty($footerNavigation)){!! $footerNavigation !!}@endif</footer>
</body>
</html>
