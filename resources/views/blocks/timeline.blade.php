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
<div class="timeline-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $items = $data['items'] ?? [];
    $layout = $data['layout'] ?? 'left';
    $lineStyle = $data['lineStyle'] ?? 'solid';
    $tsShadowPresets = ['sm' => '0 1px 2px rgba(0,0,0,0.15)', 'md' => '0 2px 4px rgba(0,0,0,0.25)', 'lg' => '0 4px 8px rgba(0,0,0,0.4)', 'outline' => '-1px -1px 0 rgba(0,0,0,0.3),1px -1px 0 rgba(0,0,0,0.3),-1px 1px 0 rgba(0,0,0,0.3),1px 1px 0 rgba(0,0,0,0.3)', 'glow' => '0 0 10px rgba(255,255,255,0.8),0 0 20px rgba(255,255,255,0.4)'];
    $titleTextShadow = $tsShadowPresets[$data['titleTextShadow'] ?? ''] ?? '';
@endphp
<style>
    .timeline-block { position: relative; padding-left: 2rem; }
    .timeline-block::before { content: ''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: var(--color-border-strong,#d1d5db); border-style: {{ $lineStyle }}; }
    .timeline-item { position: relative; margin-bottom: 1.5rem; }
    .timeline-item::before { content: ''; position: absolute; left: -2rem; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: var(--color-primary,#3b82f6); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--color-border-strong,#d1d5db); }
</style>
<div class="timeline-block">
    @foreach($items as $item)
        <div class="timeline-item">
            <div style="font-size:0.75rem;color:var(--color-text-muted,#64748b);margin-bottom:0.25rem;">{{ $item['date'] ?? '' }}</div>
            <div style="font-weight:600;{{ $titleTextShadow ? "text-shadow:{$titleTextShadow};" : '' }}">{{ $item['title'] ?? '' }}</div>
            <div style="color:var(--color-text-muted,#6b7280);font-size:0.875rem;">{{ $item['description'] ?? '' }}</div>
        </div>
    @endforeach
</div>

</div>