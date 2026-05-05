@php
    $chartType = $data['chartType'] ?? 'bar';
    $items = $data['data'] ?? [];
    $title = $data['title'] ?? '';
    $showLegend = $data['showLegend'] ?? true;
    $maxVal = max(array_column($items, 'value') ?: [1]);
    $colors = ['#3b82f6','#ef4444','#10b981','#f59e0b','#8b5cf6','#ec4899'];
    $barWidth = 40;
    $gap = 10;
    $svgWidth = count($items) * ($barWidth + $gap);
    $svgHeight = 200;
@endphp
<div>
    @if($title)
        <div style="font-weight:600;margin-bottom:0.75rem;">{{ $title }}</div>
    @endif
    <svg width="{{ $svgWidth }}" height="{{ $svgHeight + 30 }}" viewBox="0 0 {{ $svgWidth }} {{ $svgHeight + 30 }}" xmlns="http://www.w3.org/2000/svg">
        @foreach($items as $i => $item)
            @php
                $barHeight = $maxVal > 0 ? ($item['value'] / $maxVal) * $svgHeight : 0;
                $x = $i * ($barWidth + $gap);
                $y = $svgHeight - $barHeight;
                $color = $colors[$i % count($colors)];
            @endphp
            <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barWidth }}" height="{{ $barHeight }}" fill="{{ $color }}" rx="3" />
            <text x="{{ $x + $barWidth / 2 }}" y="{{ $svgHeight + 16 }}" text-anchor="middle" font-size="11" fill="#6b7280">{{ $item['label'] ?? '' }}</text>
        @endforeach
    </svg>
    @if($showLegend)
        <div style="display:flex;gap:1rem;margin-top:0.5rem;flex-wrap:wrap;">
            @foreach($items as $i => $item)
                <div style="display:flex;align-items:center;gap:4px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:{{ $colors[$i % count($colors)] }};display:inline-block;"></span>
                    <span style="font-size:0.75rem;color:#6b7280;">{{ $item['label'] ?? '' }}: {{ $item['value'] ?? 0 }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
