<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $displayName ?? $category->name }} | {{ $site->name }}</title>
    <meta name="description" content="Posts in {{ $displayName ?? $category->name }}">
    <link rel="canonical" href="{{ $baseUrl }}{{ $category->url_path }}">
    @if(!empty($rssUrl))<link rel="alternate" type="application/rss+xml" title="{{ $site->name }} Feed" href="{{ $rssUrl }}">@endif
    @if(!empty($designTokensCss))<style>{!! $designTokensCss !!}</style>@endif
    @if(!empty($criticalCss))<style>{!! $criticalCss !!}</style>@endif
    @if(!empty($customCss))<style>{!! $customCss !!}</style>@endif
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @if(!empty($headScripts)){!! $headScripts !!}@endif
    {!! $archiveJsonLd ?? '' !!}
</head>
<body class="archive-page{{ $category->featured_image ? ' has-cat-banner' : '' }}">
    <header role="banner">@if(!empty($navigation)){!! $navigation !!}@endif</header>
    @if($category->featured_image)
    <div class="cat-banner" style="position:relative;width:100%;height:clamp(150px,26vw,230px);overflow:hidden;">
        <img src="{{ $category->featured_image }}" alt="{{ $displayName ?? $category->name }}" style="width:100%;height:100%;object-fit:cover;display:block;">
        <div class="cat-banner__overlay" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(rgba(0,0,0,.28),rgba(0,0,0,.5));">
            <span class="cat-banner__title" style="color:#fff;font-weight:800;text-transform:uppercase;letter-spacing:.04em;font-size:clamp(1.5rem,3.4vw,2.6rem);text-shadow:0 2px 18px rgba(0,0,0,.55);">{{ $displayName ?? $category->name }}</span>
        </div>
    </div>
    @endif
    <main role="main" class="archive-main" style="max-width:var(--container-width,1200px);margin:0 auto;padding:var(--space-8,2rem) var(--container-padding,1rem);">
        <h1 class="archive-title">{{ $displayName ?? $category->name }}</h1>
        @if($category->description)<p style="color:var(--color-text-muted,#6b7280);margin-bottom:var(--space-6,1.5rem);">{{ $category->description }}</p>@endif

        {{-- Direct posts in this category --}}
        @if(count($posts) > 0)
        <div class="archive-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.75rem;">
        @foreach($posts as $post)
            <article class="archive-card{{ $loop->first ? ' archive-card--featured' : '' }}" style="margin:0;">
                @if($post->featured_image)
                <a href="{{ $post->url_path }}" class="archive-card__media" style="display:block;overflow:hidden;">
                    <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" loading="lazy" style="width:100%;height:200px;object-fit:cover;display:block;">
                </a>
                @endif
                <div class="archive-card__body" style="padding-top:.6rem;">
                    @if($post->category)<a href="{{ $post->category->url_path }}" class="archive-card__cat" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--color-primary,#c0392b);text-decoration:none;font-weight:600;">{{ $post->category->name }}</a>@endif
                    <h2 class="archive-card__title" style="font-size:var(--font-size-lg,1.1rem);margin:.25rem 0;line-height:1.3;"><a href="{{ $post->url_path }}" style="color:inherit;text-decoration:none;">{{ $post->title }}</a></h2>
                    @if($post->excerpt)<p style="color:var(--color-text-muted,#64748b);font-size:.9rem;">{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>@endif
                    <time style="font-size:.75rem;color:var(--color-text-muted,#9ca3af);" datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('M j, Y') }}</time>
                </div>
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
        @include('publishing._pagination')
    </main>
    <footer role="contentinfo">@if(!empty($footerNavigation)){!! $footerNavigation !!}@endif</footer>
</body>
</html>
