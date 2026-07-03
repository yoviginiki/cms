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
<div class="video-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $url = $data['url'] ?? '';
    $autoplay = !empty($data['autoplay']);
    $muted = !empty($data['muted']);
    $loop = !empty($data['loop']);
    $poster = $data['poster'] ?? '';
    $isYouTube = preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $ytMatch);
    $isVimeo = preg_match('/vimeo\.com\/(\d+)/', $url, $vmMatch);

    // Hero mode fields
    $heroMode = !empty($data['heroMode']);
    $shape = in_array($data['shape'] ?? '', ['none','capsule','circle','rounded','custom']) ? $data['shape'] : 'none';
    $shapeRadius = preg_replace('/[^a-zA-Z0-9.%\s]/', '', $data['shapeRadius'] ?? '');
    $minHeight = preg_replace('/[^a-zA-Z0-9.%]/', '', $data['minHeight'] ?? '400px');
    $overlayEnabled = !empty($data['overlay']);
    $overlayColor = \App\Support\Blocks\BlockStyle::safeColor($data['overlayColor'] ?? '') ?: 'rgba(0,0,0,0.4)';
    $overlayOpacity = max(0, min(1, (float)($data['overlayOpacity'] ?? 0.4)));
    $preTitle = e($data['preTitle'] ?? '');
    $title = e($data['title'] ?? '');
    $subtitle = e($data['subtitle'] ?? '');
    $textColor = \App\Support\Blocks\BlockStyle::safeColor($data['textColor'] ?? '') ?: '#ffffff';

    $shapeMap = [
        'none' => '',
        'capsule' => 'border-radius:999px;',
        'circle' => 'border-radius:50%;',
        'rounded' => 'border-radius:2rem;',
        'custom' => $shapeRadius ? "border-radius:{$shapeRadius};" : '',
    ];
    $shapeStyle = $shapeMap[$shape] ?? '';
@endphp
@if($heroMode && $url && !$isYouTube && !$isVimeo)
{{-- Hero video mode: background video with optional shape, overlay, and text --}}
@php $safePoster = $poster ? preg_replace('/[^a-zA-Z0-9:\/\.\-_?&=%+@~]/', '', $poster) : ''; @endphp
<div class="video-hero" style="position:relative;min-height:{{ $minHeight }};display:flex;align-items:center;justify-content:center;overflow:hidden;{{ $shapeStyle }}{{ $safePoster ? "background:transparent url({$safePoster}) center/cover no-repeat;" : '' }}">
    <video autoplay muted{{ $loop ? ' loop' : '' }} playsinline{!! $safePoster ? " poster=\"{$safePoster}\"" : '' !!}
           style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:0;">
        <source src="{{ e($url) }}" type="video/mp4">
    </video>
    @if($overlayEnabled)
    <div style="position:absolute;inset:0;background:{{ $overlayColor }};opacity:{{ $overlayOpacity }};z-index:1;"></div>
    @endif
    @if($preTitle || $title || $subtitle)
    <div style="position:relative;z-index:2;text-align:center;color:{{ $textColor }};padding:2rem;max-width:800px;">
        @if($preTitle)<p style="font-family:var(--font-heading,inherit);font-size:clamp(0.6rem,1.5vw,0.8rem);text-transform:uppercase;letter-spacing:0.3em;margin:0 0 1.5rem;opacity:0.7;">{{ $preTitle }}</p>@endif
        @if($title)<h2 style="font-family:var(--font-heading,inherit);font-size:clamp(2rem,6vw,4rem);font-weight:var(--heading-weight,400);letter-spacing:var(--letter-spacing-heading,0.1em);margin:0 0 1rem;text-transform:uppercase;">{{ $title }}</h2>@endif
        @if($subtitle)<p style="font-family:var(--font-body,inherit);font-size:clamp(0.85rem,2vw,1.1rem);font-weight:300;letter-spacing:0.08em;opacity:0.85;line-height:1.8;margin:0;">{{ $subtitle }}</p>@endif
    </div>
    @endif
</div>
@else
{{-- Standard video mode --}}
<div class="video-block" style="margin-bottom:1.5rem;{{ $shapeStyle ? "overflow:hidden;{$shapeStyle}" : '' }}">
    @if($isYouTube)
        <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">
            <iframe src="https://www.youtube-nocookie.com/embed/{{ $ytMatch[1] }}{{ $autoplay ? '?autoplay=1' : '' }}{{ $muted ? ($autoplay ? '&' : '?') . 'mute=1' : '' }}"
                    style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
                    loading="lazy" allowfullscreen title="Video"></iframe>
        </div>
    @elseif($isVimeo)
        <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;">
            <iframe src="https://player.vimeo.com/video/{{ $vmMatch[1] }}{{ $autoplay ? '?autoplay=1' : '' }}{{ $muted ? ($autoplay ? '&' : '?') . 'muted=1' : '' }}"
                    style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
                    loading="lazy" allowfullscreen title="Video"></iframe>
        </div>
    @elseif($url)
        @php
            $showControls = !$heroMode && ($data['controls'] ?? true) !== false;
            $playsinline = !empty($data['playsinline']) || $heroMode;
            $vPreload = in_array($data['preload'] ?? '', ['none', 'metadata', 'auto']) ? $data['preload'] : 'metadata';
        @endphp
        <video{{ $showControls ? ' controls' : '' }}{{ $autoplay ? ' autoplay' : '' }}{{ $muted ? ' muted' : '' }}{{ $loop ? ' loop' : '' }}{{ $playsinline ? ' playsinline' : '' }}{{ $poster ? ' poster="' . e($poster) . '"' : '' }}
               style="width:100%;max-width:100%;" preload="{{ $vPreload }}">
            <source src="{{ e($url) }}">
        </video>
    @endif
</div>
@endif

</div>