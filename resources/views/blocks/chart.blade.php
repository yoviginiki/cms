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
<div class="chart-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $chartType = $data['chartType'] ?? 'bar';
    $items = $data['data'] ?? [];
    $title = $data['title'] ?? '';
    $showLegend = $data['showLegend'] ?? true;
    $maxVal = max(array_column($items, 'value') ?: [1]);
    $colors = ['var(--chart-1,#3b82f6)','var(--chart-4,#ef4444)','var(--chart-3,#10b981)','var(--chart-2,#f59e0b)','var(--chart-5,#8b5cf6)','var(--chart-6,#ec4899)'];
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
            <text x="{{ $x + $barWidth / 2 }}" y="{{ $svgHeight + 16 }}" text-anchor="middle" font-size="11" fill="var(--color-text-muted,#6b7280)">{{ $item['label'] ?? '' }}</text>
        @endforeach
    </svg>
    @if($showLegend)
        <div style="display:flex;gap:1rem;margin-top:0.5rem;flex-wrap:wrap;">
            @foreach($items as $i => $item)
                <div style="display:flex;align-items:center;gap:4px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:{{ $colors[$i % count($colors)] }};display:inline-block;"></span>
                    <span style="font-size:0.75rem;color:var(--color-text-muted,#6b7280);">{{ $item['label'] ?? '' }}: {{ $item['value'] ?? 0 }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>

</div>