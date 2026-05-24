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
<div class="postgrid-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $categoryId = $data['categoryId'] ?? '';
    $limit = $data['limit'] ?? 6;
    $columns = $data['columns'] ?? 3;
    $cardStyle = $data['cardStyle'] ?? 'vertical';
    $showExcerpt = $data['showExcerpt'] ?? true;
    $isHorizontal = $cardStyle === 'horizontal';

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
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:1.5rem;">
    @foreach($posts as $post)
        <article style="border:1px solid var(--color-border,#e5e7eb);border-radius:0.75rem;overflow:hidden;{{ $isHorizontal ? 'display:flex;' : '' }}">
            <div style="background:#f3f4f6;{{ $isHorizontal ? 'width:33%;min-height:100px;' : 'height:160px;' }}">
                @if($post->featured_image)
                    <img src="{{ $post->featured_image }}" alt="" style="width:100%;height:100%;object-fit:cover;" />
                @endif
            </div>
            <div style="padding:1rem;{{ $isHorizontal ? 'flex:1;' : '' }}">
                <h3 style="font-weight:600;margin-bottom:0.25rem;">
                    <a href="/{{ $post->category?->slug ?? 'uncategorized' }}/{{ $post->slug }}" style="color:var(--color-text,#1e293b);text-decoration:none;">{{ $post->title }}</a>
                </h3>
                @if($showExcerpt && $post->excerpt)
                    <p style="color:#6b7280;font-size:0.875rem;">{{ \Illuminate\Support\Str::limit($post->excerpt, 120) }}</p>
                @endif
            </div>
        </article>
    @endforeach
</div>
@endif

</div>