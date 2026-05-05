@php
    $items = $data['items'] ?? [];
    $listType = $data['listType'] ?? 'bullet';
@endphp

@if($listType === 'numbered')
    <ol>
        @foreach($items as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ol>
@elseif($listType === 'checklist')
    <ul class="checklist" style="list-style: none; padding-left: 0;">
        @foreach($items as $item)
            <li style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" disabled>
                <span>{{ $item }}</span>
            </li>
        @endforeach
    </ul>
@else
    <ul>
        @foreach($items as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
@endif
