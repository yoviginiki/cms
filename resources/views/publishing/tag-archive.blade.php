<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tag->name }} | {{ $site->name }}</title>
    <meta name="description" content="Posts tagged with {{ $tag->name }}">
    <link rel="canonical" href="{{ $baseUrl }}/blog/tag/{{ $tag->slug }}">
    <meta property="og:title" content="{{ $tag->name }} | {{ $site->name }}">
    <meta property="og:type" content="website">
    <link rel="alternate" type="application/rss+xml" title="{{ $site->name }} RSS" href="{{ $baseUrl }}/feed.xml">
    @if(!empty($criticalCss))<style>{!! $criticalCss !!}</style>@endif
    @if(!empty($customCss))<style>{!! $customCss !!}</style>@endif
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    {!! $archiveJsonLd ?? '' !!}
</head>
<body>
    <header role="banner">@if(!empty($navigation)){!! $navigation !!}@endif</header>
    <main role="main" style="max-width: 800px; margin: 0 auto; padding: 2rem 1rem;">
        <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Tag: {{ $tag->name }}</h1>
        <p style="color: #6b7280; margin-bottom: 2rem;">{{ $posts->count() }} {{ $posts->count() === 1 ? 'post' : 'posts' }}</p>
        <div class="post-list">
        @foreach($posts as $post)
            <article style="margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">
                    <a href="{{ $post->url_path }}" style="color: #1a1a1a; text-decoration: none;">{{ $post->title }}</a>
                </h2>
                @if($post->excerpt)<p style="color: #4b5563; margin-bottom: 0.5rem;">{{ $post->excerpt }}</p>@endif
                <div style="font-size: 0.875rem; color: #9ca3af;">
                    <time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('M j, Y') }}</time>
                    @if($post->author) &middot; {{ $post->author->name }}@endif
                    @if($post->category) &middot; <a href="/{{ $post->category->slug }}" style="color: #6b7280;">{{ $post->category->name }}</a>@endif
                </div>
            </article>
        @endforeach
        </div>
        @if(count($posts) === 0)
            <p style="color: #6b7280;">No posts with this tag yet.</p>
        @endif
    </main>
    <footer role="contentinfo">@if(!empty($footerNavigation)){!! $footerNavigation !!}@endif</footer>
</body>
</html>
