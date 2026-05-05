@php
    $layoutClass = match($layout) {
        'grid' => 'display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;',
        'list' => '',
        'featured' => 'display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;',
        default => 'display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;',
    };
@endphp
<div class="query-posts" style="{{ $layoutClass }}">
    @foreach($posts as $i => $post)
        <article class="post-card" style="@if($cardStyle === 'overlay' && $post->featured_image) background-image:url('{{ $post->featured_image }}');background-size:cover;background-position:center;color:#fff;padding:2rem;border-radius:0.5rem;min-height:200px;display:flex;flex-direction:column;justify-content:flex-end; @else border:1px solid #e5e7eb;border-radius:0.5rem;overflow:hidden; @endif @if($layout === 'featured' && $i === 0) grid-column:1/-1; @endif">
            @if($cardStyle !== 'overlay')
                @if($post->featured_image)
                    <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" style="width:100%;height:180px;object-fit:cover;" loading="lazy">
                @endif
                <div style="padding:1rem;">
            @endif
            <h3 style="font-size:{{ $layout === 'featured' && $i === 0 ? '1.5rem' : '1.125rem' }};font-weight:600;margin-bottom:0.5rem;">
                <a href="{{ $post->url_path }}" style="color:inherit;text-decoration:none;">{{ $post->title }}</a>
            </h3>
            @if($post->excerpt)
                <p style="color:{{ $cardStyle === 'overlay' ? 'rgba(255,255,255,0.85)' : '#6b7280' }};font-size:0.875rem;margin-bottom:0.5rem;">{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>
            @endif
            <time style="font-size:0.75rem;color:{{ $cardStyle === 'overlay' ? 'rgba(255,255,255,0.7)' : '#9ca3af' }};" datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->format('M j, Y') }}</time>
            @if($cardStyle !== 'overlay')
                </div>
            @endif
        </article>
    @endforeach
</div>
