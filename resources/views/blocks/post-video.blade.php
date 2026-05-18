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
<div class="post-video-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $aspectRatio = $data['aspectRatio'] ?? '16:9';
    $autoplay = !empty($data['autoplay']);
    $controls = $data['controls'] ?? true;

    $paddingMap = ['16:9' => '56.25%', '4:3' => '75%', '1:1' => '100%'];
    $padding = $paddingMap[$aspectRatio] ?? '56.25%';

    // Dynamic: pull from template context
    $post = $__post ?? null;
    $videoUrl = $post?->video_url ?? '';
    $embedUrl = '';
    if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $videoUrl, $m)) {
        $embedUrl = "https://www.youtube-nocookie.com/embed/{$m[1]}";
    } elseif (preg_match('/youtu\.be\/([^?]+)/', $videoUrl, $m)) {
        $embedUrl = "https://www.youtube-nocookie.com/embed/{$m[1]}";
    } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $m)) {
        $embedUrl = "https://player.vimeo.com/video/{$m[1]}";
    }
    if ($embedUrl) {
        $params = [];
        if ($autoplay) $params[] = 'autoplay=1&muted=1';
        if (!$controls) $params[] = 'controls=0';
        if ($params) $embedUrl .= '?' . implode('&', $params);
    }
@endphp
@if($embedUrl)
    <div style="position:relative;padding-bottom:{{ $padding }};height:0;overflow:hidden;">
        <iframe src="{{ $embedUrl }}" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" loading="lazy" allowfullscreen title="{{ $post?->title ?? 'Video' }}"></iframe>
    </div>
@elseif($videoUrl)
    <video @if($controls) controls @endif @if($autoplay) autoplay muted @endif style="width:100%;aspect-ratio:{{ str_replace(':', '/', $aspectRatio) }};" preload="metadata">
        <source src="{{ $videoUrl }}">
    </video>
@endif

</div>
