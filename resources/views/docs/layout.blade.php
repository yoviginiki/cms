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
        .docs-search { margin: 0 0 8px; }
        .docs-search input {
            width: 100%; box-sizing: border-box; padding: 8px 10px; font-size: 13px; font-family: inherit;
            background: oklch(0.18 0.01 260); color: oklch(0.9 0.01 260);
            border: 1px solid oklch(0.3 0.01 260 / 0.6); border-radius: 6px; outline: none;
        }
        .docs-search input:focus { border-color: oklch(0.62 0.16 270); }
        .docs-search-hint { font-size: 11px; color: oklch(0.45 0.01 260); padding: 4px 2px 0; }
        .docs-content mark { background: oklch(0.8 0.15 90 / 0.35); color: inherit; border-radius: 2px; padding: 0 1px; }
        .docs-search-count { color: oklch(0.55 0.01 260) !important; }
        .docs-result { margin: 0 0 22px; }
        .docs-result-title { font-size: 16px; font-weight: 500; }
        .docs-result-file { font-size: 11px; color: oklch(0.45 0.01 260); margin-left: 8px; }
        .docs-content a.docs-hit { display: block; margin: 6px 0 0 12px; padding: 6px 10px; border-left: 2px solid oklch(0.3 0.01 260 / 0.6); color: oklch(0.7 0.01 260); font-size: 13px; line-height: 1.6; }
        .docs-content a.docs-hit:hover { text-decoration: none; background: oklch(0.25 0.01 260 / 0.25); }
        .docs-hit-heading { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: oklch(0.55 0.01 260); }
        .docs-content :target { scroll-margin-top: 16px; }
        @media (max-width: 720px) {
            .docs-layout { flex-direction: column; }
            .docs-sidebar { width: auto; height: auto; position: static; border-right: 0; border-bottom: 1px solid oklch(0.25 0.01 260 / 0.3); max-height: 45vh; }
            .docs-content { padding: 24px 16px; }
        }
    </style>
</head>
<body style="margin: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased;">
    <div class="docs-layout">
        <nav class="docs-sidebar">
            <a href="/admin/dashboard" class="docs-back">&larr; Back to CMS</a>
            <form class="docs-search" action="/docs/search" method="get" role="search">
                <input type="search" name="q" id="docs-q" value="{{ $query ?? '' }}" placeholder="Search docs…  ( / )" aria-label="Search documentation" autocomplete="off">
                <div class="docs-search-hint">Enter = search inside all docs · typing filters titles</div>
            </form>
            @if(collect($docs)->contains('slug', 'MANUAL'))
                <h3>Start here</h3>
                <a href="/docs/MANUAL" class="{{ ($current ?? '') === 'MANUAL' ? 'active' : '' }}">Manual: Build Your First Site</a>
                <a href="/docs/GUIDE-HEADER-FOOTER" class="{{ ($current ?? '') === 'GUIDE-HEADER-FOOTER' ? 'active' : '' }}">Header &amp; Footer</a>
            @endif
            <h3>Documentation</h3>
            @foreach($docs as $doc)
                <a data-doc-title="{{ mb_strtolower($doc['title'] . ' ' . $doc['slug']) }}" href="/docs/{{ $doc['slug'] }}" class="{{ ($current ?? '') === $doc['slug'] ? 'active' : '' }}">{{ $doc['title'] }}</a>
            @endforeach
            <div style="margin-top:24px;padding-top:16px;border-top:1px solid oklch(0.25 0.01 260 / 0.3);">
                <a href="/docs/download" style="display:inline-flex;align-items:center;gap:6px;padding:8px 12px;background:oklch(0.25 0.01 260);border-radius:6px;font-size:12px;color:oklch(0.7 0.01 260);text-decoration:none;">
                    &#x1F4E5; Download all (ZIP)
                </a>
            </div>
        </nav>
        <main class="docs-content">
            {!! $content !!}
        </main>
    </div>
    <script>
    (function () {
        var input = document.getElementById('docs-q');
        var links = document.querySelectorAll('[data-doc-title]');
        // Typing filters the sidebar titles; Enter runs the full-text search.
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            links.forEach(function (a) {
                a.style.display = !q || a.getAttribute('data-doc-title').indexOf(q) !== -1 ? '' : 'none';
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === '/' && document.activeElement !== input) { e.preventDefault(); input.focus(); input.select(); }
        });

        // ?hl=… (from a search result) → highlight the words in the open doc.
        var hl = new URLSearchParams(location.search).get('hl');
        var main = document.querySelector('.docs-content');
        if (!hl || !main || main.querySelector('.docs-result')) return;
        var terms = hl.toLowerCase().split(/\s+/).filter(function (t) { return t.length >= 2; });
        if (!terms.length) return;
        var re = new RegExp('(' + terms.map(function (t) { return t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + ')', 'gi');
        var walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        var first = null;
        nodes.forEach(function (node) {
            if (!re.test(node.nodeValue)) return;
            re.lastIndex = 0;
            var frag = document.createDocumentFragment();
            node.nodeValue.split(re).forEach(function (part, i) {
                if (i % 2) {
                    var m = document.createElement('mark');
                    m.textContent = part;
                    first = first || m;
                    frag.appendChild(m);
                } else if (part) {
                    frag.appendChild(document.createTextNode(part));
                }
            });
            node.parentNode.replaceChild(frag, node);
        });
        if (first && !location.hash) first.scrollIntoView({ block: 'center' });
    })();
    </script>
</body>
</html>
