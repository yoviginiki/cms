<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {!! $headContent ?? '' !!}
    @if(!empty($rssUrl))
        <link rel="alternate" type="application/rss+xml" title="{{ $siteName ?? 'RSS' }} Feed" href="{{ $rssUrl }}">
    @endif
    {{-- Self-hosted fonts — preload critical weights --}}
    <link rel="preload" href="/fonts/barlow/barlow-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/barlow/barlow-700.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/barlow/barlow-condensed-700.woff2" as="font" type="font/woff2" crossorigin>
    {{-- Theme CSS (inline for single-request PageSpeed) --}}
    <style>{!! file_get_contents(resource_path('views/publishing/themes/cytechno.css')) !!}</style>
    @if(!empty($designTokensCss))
        <style>{!! $designTokensCss !!}</style>
    @endif
    @if(!empty($customCss))
        <style>{!! $customCss !!}</style>
    @endif
    {!! $headScripts ?? '' !!}
</head>
<body>
    @include('publishing.partials.cytechno.header', ['currentSection' => $currentSection ?? ''])

    <main role="main">
        {!! $renderedBlocks ?? '' !!}
        @yield('content')
    </main>

    @include('publishing.partials.cytechno.footer')

    {{-- Entrance animation: add .js-ready to gate .fadein transitions --}}
    <script>document.documentElement.classList.add('js-ready');</script>
    {!! $bodyScripts ?? '' !!}
</body>
</html>
