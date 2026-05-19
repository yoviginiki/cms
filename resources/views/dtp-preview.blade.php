<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ e($issue['title'] ?? 'DTP Preview') }} — DTP Preview Beta</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', system-ui, sans-serif;
    background: #1a1a1a;
    color: #fff;
    min-height: 100vh;
}
.header {
    position: sticky; top: 0; z-index: 100;
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 20px; height: 44px;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.header .title { font-size: 13px; font-weight: 500; }
.header .badge { font-size: 9px; background: rgba(59,130,246,0.2); color: #93c5fd; padding: 2px 8px; border-radius: 4px; margin-left: 8px; }
.header .meta { font-size: 11px; color: rgba(255,255,255,0.4); }
.spreads {
    display: flex; flex-direction: column; align-items: center;
    gap: 40px; padding: 40px 20px 60px;
}
.spread {
    display: flex; gap: 4px;
}
.page {
    box-shadow: 0 4px 24px rgba(0,0,0,0.4);
}
.page-frame { position: absolute; overflow: hidden; }
.page-frame img { display: block; }
</style>
</head>
<body>
    <div class="header">
        <div style="display:flex;align-items:center;">
            <span class="title">{{ e($issue['title'] ?? 'Untitled') }}</span>
            <span class="badge">DTP Preview Beta</span>
        </div>
        <span class="meta">{{ $pageCount }} pages · {{ $frameCount }} frames</span>
    </div>

    <div class="spreads">
        @foreach($spreads as $spread)
        <div class="spread" data-spread-id="{{ $spread['id'] }}">
            @foreach($spread['pages'] as $page)
            <div class="page" style="{{ $page['style'] }}" data-page-id="{{ $page['id'] }}">
                @foreach($page['frames'] as $frame)
                <div class="page-frame"
                    style="{{ $frame['style'] }}"
                    data-frame-id="{{ $frame['id'] }}"
                    data-frame-type="{{ $frame['type'] }}"
                    @if($frame['locked']) data-locked="true" @endif
                >{!! $frame['html'] !!}</div>
                @endforeach
            </div>
            @endforeach
        </div>
        @endforeach

        @if(count($spreads) === 0)
        <div style="text-align:center;color:rgba(255,255,255,0.3);padding:60px;">
            <p style="font-size:16px;margin-bottom:8px;">No DTP content</p>
            <p style="font-size:12px;">Open the DTP Editor Beta to create spreads and frames.</p>
        </div>
        @endif
    </div>
</body>
</html>
