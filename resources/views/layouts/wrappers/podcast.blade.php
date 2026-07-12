{{-- Podcast: audio-first with persistent player --}}
@include('layouts.partials.site-header')

<main id="main-content" style="max-width:{{ $layout->supports['maxWidthValue'] ?? '48rem' }};margin:0 auto;padding:0 1.5rem 5rem;">
    {!! $blocksHtml !!}
</main>

@include('layouts.partials.site-footer')

{{-- Persistent bottom player --}}
<div id="podcast-player" style="position:fixed;bottom:0;left:0;right:0;background:#111;color:#fff;padding:0.75rem 1.5rem;display:none;align-items:center;gap:1rem;z-index:100;">
    <button onclick="togglePodcastPlay()" id="podcast-play-btn" style="background:none;border:none;color:#fff;font-size:1.25rem;cursor:pointer;">▶</button>
    <div style="flex:1;">
        <div id="podcast-title" style="font-size:0.8rem;font-weight:600;"></div>
        <div style="height:4px;background:#333;border-radius:2px;margin-top:0.25rem;cursor:pointer;" onclick="seekPodcast(event)">
            <div id="podcast-progress" style="height:100%;background:var(--semantic-color-brand,#3b82f6);border-radius:2px;width:0;transition:width 0.1s;"></div>
        </div>
    </div>
    <span id="podcast-time" style="font-size:0.7rem;color:#999;min-width:80px;text-align:right;"></span>
</div>

<script>
var podcastAudio = null;
function initPodcastPlayer(src, title) {
    podcastAudio = new Audio(src);
    document.getElementById('podcast-title').textContent = title;
    document.getElementById('podcast-player').style.display = 'flex';
    podcastAudio.addEventListener('timeupdate', function() {
        var p = podcastAudio.duration ? (podcastAudio.currentTime / podcastAudio.duration) * 100 : 0;
        document.getElementById('podcast-progress').style.width = p + '%';
        var cur = formatTime(podcastAudio.currentTime), dur = formatTime(podcastAudio.duration || 0);
        document.getElementById('podcast-time').textContent = cur + ' / ' + dur;
    });
}
function togglePodcastPlay() {
    if (!podcastAudio) return;
    if (podcastAudio.paused) { podcastAudio.play(); document.getElementById('podcast-play-btn').textContent = '⏸'; }
    else { podcastAudio.pause(); document.getElementById('podcast-play-btn').textContent = '▶'; }
}
function seekPodcast(e) {
    if (!podcastAudio) return;
    var rect = e.currentTarget.getBoundingClientRect();
    podcastAudio.currentTime = ((e.clientX - rect.left) / rect.width) * podcastAudio.duration;
}
function formatTime(s) { var m = Math.floor(s/60), sec = Math.floor(s%60); return m + ':' + (sec < 10 ? '0' : '') + sec; }
// Auto-detect audio elements and init player
document.addEventListener('DOMContentLoaded', function() {
    var audio = document.querySelector('audio[src], audio source');
    if (audio) initPodcastPlayer(audio.src || audio.querySelector('source')?.src, document.title);
});
</script>
