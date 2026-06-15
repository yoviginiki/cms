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
<div class="map-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $lat = $data['lat'] ?? 42.6977;
    $lng = $data['lng'] ?? 23.3219;
    $zoom = $data['zoom'] ?? 13;
    $markerLabel = $data['markerLabel'] ?? '';
    $height = $data['height'] ?? '400px';
@endphp
<div style="height:{{ $height }};background:var(--color-border,#e5e7eb);display:flex;align-items:center;justify-content:center;border-radius:var(--border-radius-md,0.5rem);">
    <a href="https://maps.google.com/?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener noreferrer" style="color:#3b82f6;text-decoration:underline;">
        @if($markerLabel)
            {{ $markerLabel }} &mdash;
        @endif
        View on Google Maps ({{ $lat }}, {{ $lng }})
    </a>
</div>

</div>