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
<div class="dropcap-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $capSize = $data['capSize'] ?? 3;
    $capColor = $data['capColor'] ?? null;
    $content = $data['content'] ?? '';
@endphp

<div class="dropcap" style="--cap-size: {{ $capSize }}; {{ $capColor ? '--cap-color: ' . $capColor . ';' : '' }}">
    <style>
        .dropcap::first-letter {
            float: left;
            font-size: calc(var(--cap-size) * 1em);
            line-height: 0.8;
            padding-right: 0.1em;
            font-weight: bold;
            color: var(--cap-color, var(--color-text));
        }
    </style>
    {!! $content !!}
</div>

</div>