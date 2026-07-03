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
<div class="audio-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
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
        @php
            $aPreload = in_array($data['preload'] ?? '', ['none', 'metadata', 'auto']) ? $data['preload'] : 'metadata';
            $aLoop = !empty($data['loop']);
            // clamp volume; applied via data attr + tiny inline init (no autoplay, ever)
            $aVolume = isset($data['volume']) && is_numeric($data['volume']) ? max(0, min(1, (float) $data['volume'])) : null;
        @endphp
        <audio controls preload="{{ $aPreload }}"{{ $aLoop ? ' loop' : '' }} style="width:100%;"
               @if($aVolume !== null) data-volume="{{ $aVolume }}" @endif>
            <source src="{{ e($url) }}">
            Your browser does not support the audio element.
        </audio>
        @if($aVolume !== null)
            <script>(function(){var s=document.currentScript,a=s&&s.previousElementSibling;if(a&&a.tagName==='AUDIO'){a.volume=parseFloat(a.dataset.volume||'1');}})();</script>
        @endif
    @endif
</div>

</div>