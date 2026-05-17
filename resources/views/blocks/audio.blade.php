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
<div class="audio-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $url = $data['url'] ?? '';
    $title = $data['title'] ?? '';
    $artist = $data['artist'] ?? '';
@endphp
<div class="audio-block">
    @if(!empty($title) || !empty($artist))
        <div class="audio-block__info" style="margin-bottom:0.5rem;">
            @if(!empty($title))<p style="font-weight:600;margin:0;">{{ e($title) }}</p>@endif
            @if(!empty($artist))<p style="color:#666;font-size:0.875rem;margin:0;">{{ e($artist) }}</p>@endif
        </div>
    @endif
    @if(!empty($url))
        <audio controls preload="metadata" style="width:100%;">
            <source src="{{ e($url) }}">
            Your browser does not support the audio element.
        </audio>
    @endif
</div>

</div>