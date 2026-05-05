@php
    $items = $data['items'] ?? [];
    $columns = $data['columns'] ?? 3;
    $style = $data['style'] ?? 'icon-top';
    $flexDir = $style === 'icon-left' ? 'row' : 'column';
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:1.5rem;">
    @foreach($items as $item)
        <div style="display:flex;flex-direction:{{ $flexDir }};align-items:{{ $style === 'icon-left' ? 'flex-start' : 'center' }};gap:0.75rem;padding:1.5rem;border:1px solid #e5e7eb;border-radius:0.5rem;text-align:{{ $style === 'icon-left' ? 'left' : 'center' }};">
            <div style="font-size:1.5rem;">{{ $item['icon'] ?? '' }}</div>
            <div>
                <div style="font-weight:600;margin-bottom:0.25rem;">{{ $item['title'] ?? '' }}</div>
                <div style="color:#6b7280;font-size:0.875rem;">{{ $item['description'] ?? '' }}</div>
            </div>
        </div>
    @endforeach
</div>
