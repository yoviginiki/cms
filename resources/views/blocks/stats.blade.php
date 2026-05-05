@php
    $items = $data['items'] ?? [];
    $columns = $data['columns'] ?? 3;
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:1.5rem;text-align:center;">
    @foreach($items as $item)
        <div style="padding:1.5rem;">
            <div style="font-size:2.5rem;font-weight:700;line-height:1;">{{ $item['prefix'] ?? '' }}{{ $item['value'] ?? '' }}{{ $item['suffix'] ?? '' }}</div>
            <div style="color:#6b7280;font-size:0.875rem;margin-top:0.5rem;">{{ $item['label'] ?? '' }}</div>
        </div>
    @endforeach
</div>
