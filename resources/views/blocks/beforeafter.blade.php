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
<div class="beforeafter-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $beforeSrc = $data['beforeSrc'] ?? '';
    $afterSrc = $data['afterSrc'] ?? '';
    $beforeLabel = $data['beforeLabel'] ?? 'Before';
    $afterLabel = $data['afterLabel'] ?? 'After';
    $initialPosition = $data['initialPosition'] ?? 50;
@endphp
<div class="beforeafter-block" style="position:relative;overflow:hidden;max-width:100%;">
    @if(!empty($afterSrc))
        <img src="{{ e($afterSrc) }}" alt="{{ e($afterLabel) }}" loading="lazy" style="display:block;width:100%;height:auto;">
    @endif
    @if(!empty($beforeSrc))
        <div class="beforeafter-before" style="position:absolute;inset:0;overflow:hidden;width:{{ (int)$initialPosition }}%;">
            <img src="{{ e($beforeSrc) }}" alt="{{ e($beforeLabel) }}" loading="lazy" style="display:block;width:100%;height:100%;object-fit:cover;">
        </div>
    @endif
    <input
        type="range"
        min="0"
        max="100"
        value="{{ (int)$initialPosition }}"
        aria-label="Comparison slider"
        style="position:absolute;bottom:0;left:0;width:100%;margin:0;opacity:0.6;cursor:pointer;z-index:2;"
        oninput="this.parentElement.querySelector('.beforeafter-before').style.width=this.value+'%'"
    >
    <span style="position:absolute;top:0.5rem;left:0.5rem;background:rgba(0,0,0,0.5);color:#fff;font-size:0.75rem;padding:0.25rem 0.5rem;border-radius:4px;">{{ e($beforeLabel) }}</span>
    <span style="position:absolute;top:0.5rem;right:0.5rem;background:rgba(0,0,0,0.5);color:#fff;font-size:0.75rem;padding:0.25rem 0.5rem;border-radius:4px;">{{ e($afterLabel) }}</span>
</div>

</div>