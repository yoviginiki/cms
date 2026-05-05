@php
    $limit = $data['limit'] ?? 3;
    $basedOn = $data['basedOn'] ?? 'category';
    // $posts would be populated at build time with related posts
    $posts = $posts ?? [];
@endphp
<div style="display:grid;grid-template-columns:repeat({{ min($limit, 4) }},1fr);gap:1.5rem;">
    @foreach($posts as $post)
        <article style="border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;">
            @if(!empty($post['image']))
                <img src="{{ $post['image'] }}" alt="" style="width:100%;height:140px;object-fit:cover;" />
            @else
                <div style="height:140px;background:#f3f4f6;"></div>
            @endif
            <div style="padding:1rem;">
                <h3 style="font-weight:600;font-size:0.875rem;margin:0;">
                    <a href="{{ $post['url'] ?? '#' }}" style="color:inherit;text-decoration:none;">{{ $post['title'] ?? '' }}</a>
                </h3>
                <div style="font-size:0.75rem;color:#9ca3af;margin-top:0.25rem;">{{ $post['date'] ?? '' }}</div>
            </div>
        </article>
    @endforeach
</div>
