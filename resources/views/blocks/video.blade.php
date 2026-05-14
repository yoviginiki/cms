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
<div class="video-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $url = $data['url'] ?? '';
    $autoplay = !empty($data['autoplay']);
    $muted = !empty($data['muted']);
    $poster = $data['poster'] ?? '';
    $isYouTube = preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $ytMatch);
    $isVimeo = preg_match('/vimeo\.com\/(\d+)/', $url, $vmMatch);
@endphp
<div class="video-block" style="margin-bottom: 1.5rem;">
    @if($isYouTube)
        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;">
            <iframe src="https://www.youtube-nocookie.com/embed/{{ $ytMatch[1] }}{{ $autoplay ? '?autoplay=1' : '' }}{{ $muted ? ($autoplay ? '&' : '?') . 'mute=1' : '' }}"
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                    loading="lazy" allowfullscreen title="Video"></iframe>
        </div>
    @elseif($isVimeo)
        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;">
            <iframe src="https://player.vimeo.com/video/{{ $vmMatch[1] }}{{ $autoplay ? '?autoplay=1' : '' }}{{ $muted ? ($autoplay ? '&' : '?') . 'muted=1' : '' }}"
                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                    loading="lazy" allowfullscreen title="Video"></iframe>
        </div>
    @elseif($url)
        <video controls{{ $autoplay ? ' autoplay' : '' }}{{ $muted ? ' muted' : '' }}{{ $poster ? ' poster="' . e($poster) . '"' : '' }}
               style="width: 100%; max-width: 100%;" preload="metadata">
            <source src="{{ e($url) }}">
        </video>
    @endif
</div>

</div>