@php
    $postId = $data['postId'] ?? '';
    $style = $data['style'] ?? 'vertical';
    $showExcerpt = $data['showExcerpt'] ?? true;
    $showDate = $data['showDate'] ?? true;
    $showCategory = $data['showCategory'] ?? true;
    $isHorizontal = $style === 'horizontal';
    // Post data would be populated at build time
    $post = $post ?? null;
@endphp
<article style="border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;{{ $isHorizontal ? 'display:flex;' : '' }}">
    <div style="background:#f3f4f6;{{ $isHorizontal ? 'width:33%;min-height:120px;' : 'height:200px;' }}">
        @if($post && !empty($post['image']))
            <img src="{{ $post['image'] }}" alt="" style="width:100%;height:100%;object-fit:cover;" />
        @endif
    </div>
    <div style="padding:1.25rem;{{ $isHorizontal ? 'flex:1;' : '' }}">
        @if($showCategory)
            <div style="font-size:0.75rem;color:#3b82f6;font-weight:500;margin-bottom:0.25rem;">{{ $post['category'] ?? 'Category' }}</div>
        @endif
        <h3 style="font-weight:600;margin-bottom:0.25rem;">{{ $post['title'] ?? 'Post Title' }}</h3>
        @if($showDate)
            <div style="font-size:0.75rem;color:#9ca3af;margin-bottom:0.5rem;">{{ $post['date'] ?? '' }}</div>
        @endif
        @if($showExcerpt)
            <p style="color:#6b7280;font-size:0.875rem;">{{ $post['excerpt'] ?? '' }}</p>
        @endif
    </div>
</article>
