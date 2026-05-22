<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magazines | {{ $site?->name ?? 'Magazine' }}</title>
    <link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Inter'; src: url('/fonts/inter.woff2') format('woff2'); font-weight: 100 900; font-display: swap; }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; background: #fafafa; color: #1a1a1a; }
        .container { max-width: 1200px; margin: 0 auto; padding: 80px 24px; }
        h1 { font-size: 32px; font-weight: 500; margin-bottom: 8px; }
        .subtitle { font-size: 15px; color: #888; margin-bottom: 48px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 32px; }
        .card { background: #fff; border: 1px solid #eee; overflow: hidden; transition: box-shadow 0.3s; text-decoration: none; color: inherit; display: block; }
        .card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .card .cover { aspect-ratio: 3/4; background: #f0f0f0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .card .cover img { width: 100%; height: 100%; object-fit: cover; }
        .card .cover .placeholder { font-size: 48px; color: #ddd; }
        .card .info { padding: 20px; }
        .card .info h2 { font-size: 16px; font-weight: 500; margin-bottom: 4px; }
        .card .info p { font-size: 13px; color: #888; }
        .empty { text-align: center; padding: 120px 24px; color: #aaa; font-size: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Magazines</h1>
        @php
            $totalCount = $magazines->count() + (isset($dtpIssues) ? $dtpIssues->count() : 0);
        @endphp
        <p class="subtitle">{{ $totalCount }} {{ $totalCount === 1 ? 'issue' : 'issues' }} published</p>

        @if($totalCount === 0)
            <div class="empty">No magazines published yet.</div>
        @else
            <div class="grid">
                {{-- DTP Issues --}}
                @if(isset($dtpIssues))
                @foreach($dtpIssues as $issue)
                    <a href="/magazine/dtp/{{ $issue->id }}" class="card">
                        <div class="cover">
                            <span class="placeholder">&#x1F4D6;</span>
                        </div>
                        <div class="info">
                            <h2>{{ $issue->title ?? 'Untitled' }}</h2>
                            @if($issue->subtitle)
                                <p>{{ Str::limit($issue->subtitle, 100) }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
                @endif

                {{-- Legacy magazines --}}
                @foreach($magazines as $mag)
                    <a href="/magazine/{{ $mag->slug }}" class="card">
                        <div class="cover">
                            @if($mag->cover_image)
                                <img src="{{ $mag->cover_image }}" alt="{{ $mag->title }}">
                            @else
                                <span class="placeholder">&#x1F4D6;</span>
                            @endif
                        </div>
                        <div class="info">
                            <h2>{{ $mag->title }}</h2>
                            @if($mag->description)
                                <p>{{ Str::limit($mag->description, 100) }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
