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
