@php
    $limit = $data['limit'] ?? 5;
    $layout = $data['layout'] ?? 'list';
    $showImage = $data['showImage'] ?? true;
    // $posts would be populated at build time with latest posts
    $posts = $posts ?? [];
@endphp
@if($layout === 'cards')
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;">
        @foreach($posts as $post)
            <article style="border:1px solid #e5e7eb;border-radius:0.75rem;overflow:hidden;">
                @if($showImage && !empty($post['image']))
                    <img src="{{ $post['image'] }}" alt="" style="width:100%;height:160px;object-fit:cover;" />
                @endif
                <div style="padding:1rem;">
                    <h3 style="font-weight:600;">{{ $post['title'] ?? '' }}</h3>
                    <div style="font-size:0.75rem;color:#9ca3af;">{{ $post['date'] ?? '' }}</div>
                </div>
            </article>
        @endforeach
    </div>
@elseif($layout === 'compact')
    <ul style="list-style:none;padding:0;margin:0;">
        @foreach($posts as $post)
            <li style="padding:0.5rem 0;border-bottom:1px solid #f3f4f6;">
                <a href="{{ $post['url'] ?? '#' }}" style="color:inherit;text-decoration:none;font-size:0.875rem;">{{ $post['title'] ?? '' }}</a>
            </li>
        @endforeach
    </ul>
@else
    <div>
        @foreach($posts as $post)
            <div style="display:flex;align-items:center;gap:1rem;padding:0.75rem 0;border-bottom:1px solid #f3f4f6;">
                @if($showImage && !empty($post['image']))
                    <img src="{{ $post['image'] }}" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:0.375rem;" />
                @endif
                <div>
                    <h3 style="font-weight:600;margin:0;">{{ $post['title'] ?? '' }}</h3>
                    <div style="font-size:0.75rem;color:#9ca3af;">{{ $post['date'] ?? '' }}</div>
                </div>
            </div>
        @endforeach
    </div>
@endif
