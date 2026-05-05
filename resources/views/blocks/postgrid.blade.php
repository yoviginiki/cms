@php
    $categoryId = $data['categoryId'] ?? '';
    $limit = $data['limit'] ?? 6;
    $columns = $data['columns'] ?? 3;
    $cardStyle = $data['cardStyle'] ?? 'vertical';
    $showExcerpt = $data['showExcerpt'] ?? true;
    $isHorizontal = $cardStyle === 'horizontal';
    // $posts would be populated at build time
    $posts = $posts ?? [];
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:1.5rem;">
    @foreach($posts as $post)
        <article style="border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;{{ $isHorizontal ? 'display:flex;' : '' }}">
            <div style="background:#f3f4f6;{{ $isHorizontal ? 'width:33%;min-height:100px;' : 'height:160px;' }}">
                @if(!empty($post['image']))
                    <img src="{{ $post['image'] }}" alt="" style="width:100%;height:100%;object-fit:cover;" />
                @endif
            </div>
            <div style="padding:1rem;{{ $isHorizontal ? 'flex:1;' : '' }}">
                <h3 style="font-weight:600;margin-bottom:0.25rem;">{{ $post['title'] ?? '' }}</h3>
                @if($showExcerpt)
                    <p style="color:#6b7280;font-size:0.875rem;">{{ $post['excerpt'] ?? '' }}</p>
                @endif
            </div>
        </article>
    @endforeach
</div>
