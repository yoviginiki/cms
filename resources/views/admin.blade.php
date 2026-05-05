<!DOCTYPE html>
<html lang="en" data-theme="cms-admin">
<script>try{var t=localStorage.getItem('admin-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} — Admin</title>
    <link rel="preload" href="/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
    @php
        $manifest = json_decode(file_get_contents(public_path('admin-assets/.vite/manifest.json')), true);
        $entry = $manifest['index.html'] ?? [];
    @endphp
    @if(!empty($entry['css']))
        @foreach($entry['css'] as $css)
            <link rel="stylesheet" href="/admin-assets/{{ $css }}">
        @endforeach
    @endif
</head>
<body>
    <div id="root"></div>
    <script>
        window.__APP__ = {!! json_encode([
            'user' => auth()->user()?->load('tenant'),
            'csrfToken' => csrf_token(),
        ]) !!};
    </script>
    @if(!empty($entry['file']))
        <script type="module" src="/admin-assets/{{ $entry['file'] }}"></script>
    @endif
</body>
</html>
