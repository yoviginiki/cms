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
<div class="logostrip-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $logos = $data['logos'] ?? [];
    $grayscale = $data['grayscale'] ?? true;
    $columns = $data['columns'] ?? 4;
    $gap = $data['gap'] ?? '32px';
@endphp
<div class="logostrip-block" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:{{ e($gap) }};">
    @foreach($logos as $url)
        @if(!empty($url))
            <img src="{{ e($url) }}" alt="" loading="lazy" style="height:3rem;object-fit:contain;@if($grayscale)filter:grayscale(100%);@endif">
        @endif
    @endforeach
</div>

</div>