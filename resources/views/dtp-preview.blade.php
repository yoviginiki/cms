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
    display: flex; gap: 0;
    box-shadow: 0 4px 24px rgba(0,0,0,0.4);
}
.spread.single-layout .page { box-shadow: 0 4px 24px rgba(0,0,0,0.4); }
.page {
    position: relative;
    color: #1a1a1a;
    overflow: hidden;
}
.page-frame { position: absolute; overflow: hidden; }
.page-frame img { max-width: 100%; height: auto; }
.page-frame img { display: block; }

/* Book mode navigation */
.book-nav {
    position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
    z-index: 200;
    display: flex; align-items: center; gap: 12px;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(12px);
    padding: 8px 20px; border-radius: 24px;
    border: 1px solid rgba(255,255,255,0.1);
}
.book-nav button {
    background: rgba(255,255,255,0.1); border: none; color: #fff;
    padding: 6px 14px; border-radius: 6px; cursor: pointer;
    font-size: 12px; font-weight: 500;
    transition: background 0.15s;
}
.book-nav button:hover { background: rgba(255,255,255,0.2); }
.book-nav button:disabled { opacity: 0.3; cursor: default; }
.book-nav .page-info { font-size: 11px; color: rgba(255,255,255,0.5); min-width: 80px; text-align: center; }

/* Presentation mode */
.presentation-mode .spreads {
    gap: 0; padding: 0;
}
.presentation-mode .spread {
    display: none;
    box-shadow: none;
}
.presentation-mode .spread.active {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 44px);
}
</style>
</head>
<body class="{{ $layoutMode === 'presentation' ? 'presentation-mode' : '' }}">
    <div class="header">
        <div style="display:flex;align-items:center;">
            <span class="title">{{ e($issue['title'] ?? 'Untitled') }}</span>
            <span class="badge">DTP Preview Beta</span>
            @if($layoutMode !== 'single')
                <span class="badge" style="background:rgba(168,85,247,0.2);color:#c4b5fd;">{{ ucfirst($layoutMode) }} mode</span>
            @endif
        </div>
        <span class="meta">{{ $pageCount }} pages · {{ $frameCount }} frames</span>
    </div>

    <div class="spreads">
        @foreach($spreads as $sIdx => $spread)
        <div class="spread {{ $layoutMode === 'single' ? 'single-layout' : '' }} {{ $sIdx === 0 ? 'active' : '' }}"
             data-spread-id="{{ $spread['id'] }}" data-spread-index="{{ $sIdx }}">
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

    @if(($layoutMode === 'book' || $layoutMode === 'presentation') && count($spreads) > 1)
    <div class="book-nav">
        <button id="prevSpread" onclick="navigateSpread(-1)">&#9664; Prev</button>
        <span class="page-info" id="spreadInfo">1 / {{ count($spreads) }}</span>
        <button id="nextSpread" onclick="navigateSpread(1)">Next &#9654;</button>
    </div>
    <script>
    (function() {
        var currentSpread = 0;
        var spreads = document.querySelectorAll('.spread');
        var total = spreads.length;
        var isPresentation = {{ $layoutMode === 'presentation' ? 'true' : 'false' }};

        function update() {
            if (isPresentation) {
                spreads.forEach(function(s, i) {
                    s.classList.toggle('active', i === currentSpread);
                });
            } else {
                // Book mode — smooth scroll to current spread
                spreads[currentSpread].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            document.getElementById('spreadInfo').textContent = (currentSpread + 1) + ' / ' + total;
            document.getElementById('prevSpread').disabled = currentSpread === 0;
            document.getElementById('nextSpread').disabled = currentSpread === total - 1;
        }

        window.navigateSpread = function(dir) {
            var next = currentSpread + dir;
            if (next >= 0 && next < total) {
                currentSpread = next;
                update();
            }
        };

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { window.navigateSpread(1); e.preventDefault(); }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { window.navigateSpread(-1); e.preventDefault(); }
        });

        update();
    })();
    </script>
    @endif
</body>
</html>
