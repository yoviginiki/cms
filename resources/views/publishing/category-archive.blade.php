<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $category->name }} | {{ $site->name }}</title>
    <meta name="description" content="Posts in {{ $category->name }}">
    <link rel="canonical" href="{{ $baseUrl }}/{{ $category->slug }}">
    @if(!empty($rssUrl))<link rel="alternate" type="application/rss+xml" title="{{ $site->name }} Feed" href="{{ $rssUrl }}">@endif
    @if(!empty($designTokensCss))<style>{!! $designTokensCss !!}</style>@endif
    @if(!empty($criticalCss))<style>{!! $criticalCss !!}</style>@endif
    @if(!empty($customCss))<style>{!! $customCss !!}</style>@endif
    {!! $archiveJsonLd ?? '' !!}
</head>
<body>
    <header role="banner">@if(!empty($navigation)){!! $navigation !!}@endif</header>
    <main role="main" style="max-width:var(--container-width,800px);margin:0 auto;padding:var(--space-8,2rem) var(--container-padding,1rem);">
        <h1>{{ $category->name }}</h1>
        @if($category->description)<p style="color:var(--color-text-muted,#6b7280);margin-bottom:var(--space-6,1.5rem);">{{ $category->description }}</p>@endif

        {{-- Direct posts in this category --}}
        @if(count($posts) > 0)
        <div class="post-list">
        @foreach($posts as $post)
            <article style="margin-bottom:var(--space-8,2rem);padding-bottom:var(--space-8,2rem);border-bottom:1px solid var(--color-border,#e5e7eb);">
                <h2 style="font-size:var(--font-size-xl,1.25rem);margin-bottom:var(--space-2,0.5rem);"><a href="{{ $post->url_path }}">{{ $post->title }}</a></h2>
                @if($post->excerpt)<p style="color:var(--color-text-muted,#64748b);">{{ $post->excerpt }}</p>@endif
                <time style="font-size:var(--font-size-sm,0.875rem);color:var(--color-text-muted,#9ca3af);" datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('M j, Y') }}</time>
            </article>
        @endforeach
        </div>
        @endif

        {{-- Child categories with their posts --}}
        @if(!empty($childCategories))
        @foreach($childCategories as $child)
            <section style="margin-top:var(--space-8,2rem);">
                <h2 style="font-size:var(--font-size-lg,1.125rem);margin-bottom:var(--space-4,1rem);padding-bottom:var(--space-2,0.5rem);border-bottom:2px solid var(--color-border,#e5e7eb);">
                    <a href="/{{ $child['category']->slug }}" style="text-decoration:none;color:inherit;">{{ $child['category']->name }}</a>
                </h2>
                @foreach($child['posts'] as $post)
                    <article style="margin-bottom:var(--space-6,1.5rem);padding-left:var(--space-4,1rem);border-left:3px solid var(--color-border,#e5e7eb);">
                        <h3 style="font-size:var(--font-size-base,1rem);margin-bottom:var(--space-1,0.25rem);"><a href="{{ $post->url_path }}">{{ $post->title }}</a></h3>
                        @if($post->excerpt)<p style="color:var(--color-text-muted,#64748b);font-size:var(--font-size-sm,0.875rem);">{{ $post->excerpt }}</p>@endif
                        <time style="font-size:var(--font-size-xs,0.75rem);color:var(--color-text-muted,#9ca3af);" datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('M j, Y') }}</time>
                    </article>
                @endforeach
            </section>
        @endforeach
        @endif

        @if(count($posts) === 0 && empty($childCategories))<p style="color:var(--color-text-muted,#6b7280);">No posts in this category yet.</p>@endif
    </main>
    <footer role="contentinfo">@if(!empty($footerNavigation)){!! $footerNavigation !!}@endif</footer>
</body>
</html>
