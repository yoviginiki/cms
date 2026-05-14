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
<div class="featuregrid-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $items = $data['items'] ?? [];
    $columns = $data['columns'] ?? 3;
    $style = $data['style'] ?? 'icon-top';
    $flexDir = $style === 'icon-left' ? 'row' : 'column';
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:1.5rem;">
    @foreach($items as $item)
        <div style="display:flex;flex-direction:{{ $flexDir }};align-items:{{ $style === 'icon-left' ? 'flex-start' : 'center' }};gap:0.75rem;padding:1.5rem;border:1px solid var(--color-border,#e2e8f0);border-radius:var(--border-radius-md,0.5rem);text-align:{{ $style === 'icon-left' ? 'left' : 'center' }};">
            <div style="font-size:1.5rem;">{{ $item['icon'] ?? '' }}</div>
            <div>
                <div style="font-weight:600;margin-bottom:0.25rem;">{{ $item['title'] ?? '' }}</div>
                <div style="color:#6b7280;font-size:0.875rem;">{{ $item['description'] ?? '' }}</div>
            </div>
        </div>
    @endforeach
</div>

</div>