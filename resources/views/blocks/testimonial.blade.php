@php
    $items = $data['items'] ?? [];
    $layout = $data['layout'] ?? 'single';
    $gridStyle = $layout === 'grid' ? 'display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;' : '';
@endphp
<div style="{{ $gridStyle }}">
    @foreach($items as $item)
        <blockquote style="border:1px solid #e5e7eb;border-radius:0.75rem;padding:1.5rem;margin-bottom:{{ $layout === 'grid' ? '0' : '1rem' }};">
            <p style="font-style:italic;color:#374151;margin-bottom:1rem;">&ldquo;{{ $item['quote'] ?? '' }}&rdquo;</p>
            <div style="display:flex;align-items:center;gap:0.75rem;">
                @if(!empty($item['avatar']))
                    <img src="{{ $item['avatar'] }}" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" />
                @endif
                <div>
                    <div style="font-weight:600;">{{ $item['author'] ?? '' }}</div>
                    <div style="color:#6b7280;font-size:0.875rem;">{{ $item['role'] ?? '' }}</div>
                </div>
            </div>
        </blockquote>
    @endforeach
</div>
