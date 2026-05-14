@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba);
    $__customClass = BlockStyle::safeClass($__adv['customClass'] ?? '');
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="timeline-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $items = $data['items'] ?? [];
    $layout = $data['layout'] ?? 'left';
    $lineStyle = $data['lineStyle'] ?? 'solid';
@endphp
<style>
    .timeline-block { position: relative; padding-left: 2rem; }
    .timeline-block::before { content: ''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: #d1d5db; border-style: {{ $lineStyle }}; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; }
    .timeline-item::before { content: ''; position: absolute; left: -2rem; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: #3b82f6; border: 2px solid #fff; box-shadow: 0 0 0 2px #d1d5db; }
</style>
<div class="timeline-block">
    @foreach($items as $item)
        <div class="timeline-item">
            <div style="font-size:0.75rem;color:#9ca3af;margin-bottom:0.25rem;">{{ $item['date'] ?? '' }}</div>
            <div style="font-weight:600;">{{ $item['title'] ?? '' }}</div>
            <div style="color:#6b7280;font-size:0.875rem;">{{ $item['description'] ?? '' }}</div>
        </div>
    @endforeach
</div>

</div>