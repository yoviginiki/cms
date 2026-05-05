<!DOCTYPE html>
<html lang="en" data-theme="cms-admin">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Documentation' }} — Ensodo CMS</title>
    <link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
    <script>try{var t=localStorage.getItem('admin-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    @php
        $manifest = json_decode(file_get_contents(public_path('admin-assets/.vite/manifest.json')), true);
        $entry = $manifest['index.html'] ?? [];
    @endphp
    @if(!empty($entry['css']))
        @foreach($entry['css'] as $css)
            <link rel="stylesheet" href="/admin-assets/{{ $css }}">
        @endforeach
    @endif
    <style>
        .docs-layout { display: flex; min-height: 100vh; }
        .docs-sidebar {
            width: 220px; padding: 24px 16px; border-right: 1px solid oklch(0.25 0.01 260 / 0.3);
            position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .docs-sidebar a {
            display: block; padding: 6px 12px; border-radius: 6px; font-size: 13px;
            color: oklch(0.7 0.01 260); text-decoration: none; margin-bottom: 2px;
        }
        .docs-sidebar a:hover { background: oklch(0.25 0.01 260 / 0.3); color: oklch(0.9 0.01 260); }
        .docs-sidebar a.active { background: oklch(0.62 0.16 270 / 0.12); color: oklch(0.62 0.16 270); }
        .docs-sidebar h3 {
            font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.08em;
            color: oklch(0.45 0.01 260); padding: 16px 12px 6px; margin: 0;
        }
        .docs-content {
            flex: 1; padding: 40px 48px; max-width: 820px; overflow-y: auto;
        }
        .docs-content h1 { font-size: 24px; font-weight: 500; margin: 0 0 8px; color: oklch(0.92 0.01 260); }
        .docs-content h2 { font-size: 18px; font-weight: 500; margin: 32px 0 12px; color: oklch(0.88 0.01 260); padding-bottom: 8px; border-bottom: 1px solid oklch(0.25 0.01 260 / 0.3); }
        .docs-content h3 { font-size: 15px; font-weight: 500; margin: 24px 0 8px; color: oklch(0.82 0.01 260); }
        .docs-content p { font-size: 14px; line-height: 1.7; color: oklch(0.7 0.01 260); margin: 0 0 12px; }
        .docs-content ul, .docs-content ol { font-size: 14px; line-height: 1.7; color: oklch(0.7 0.01 260); padding-left: 20px; margin: 0 0 12px; }
        .docs-content li { margin-bottom: 4px; }
        .docs-content code {
            font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 12px;
            background: oklch(0.2 0.01 260); padding: 2px 6px; border-radius: 4px; color: oklch(0.85 0.01 260);
        }
        .docs-content pre {
            background: oklch(0.13 0.01 260); border: 1px solid oklch(0.25 0.01 260 / 0.3);
            border-radius: 8px; padding: 16px; overflow-x: auto; margin: 0 0 16px;
        }
        .docs-content pre code { background: none; padding: 0; font-size: 12px; line-height: 1.6; }
        .docs-content table { width: 100%; border-collapse: collapse; margin: 0 0 16px; font-size: 13px; }
        .docs-content th { text-align: left; padding: 8px 12px; border-bottom: 1px solid oklch(0.25 0.01 260 / 0.5); color: oklch(0.6 0.01 260); font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        .docs-content td { padding: 8px 12px; border-bottom: 1px solid oklch(0.25 0.01 260 / 0.15); color: oklch(0.75 0.01 260); }
        .docs-content td code { font-size: 11px; }
        .docs-content a { color: oklch(0.62 0.16 270); text-decoration: none; }
        .docs-content a:hover { text-decoration: underline; }
        .docs-content strong { font-weight: 500; color: oklch(0.85 0.01 260); }
        .docs-content blockquote {
            border-left: 3px solid oklch(0.62 0.16 270 / 0.4); padding: 8px 16px; margin: 0 0 12px;
            color: oklch(0.65 0.01 260); font-style: italic;
        }
        .docs-back { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: oklch(0.55 0.01 260); text-decoration: none; margin-bottom: 24px; }
        .docs-back:hover { color: oklch(0.8 0.01 260); }
    </style>
</head>
<body style="margin: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased;">
    <div class="docs-layout">
        <nav class="docs-sidebar">
            <a href="/admin/dashboard" class="docs-back">&larr; Back to CMS</a>
            <h3>Documentation</h3>
            @foreach($docs as $doc)
                <a href="/docs/{{ $doc['slug'] }}" class="{{ ($current ?? '') === $doc['slug'] ? 'active' : '' }}">{{ $doc['title'] }}</a>
            @endforeach
        </nav>
        <main class="docs-content">
            {!! $content !!}
        </main>
    </div>
</body>
</html>
