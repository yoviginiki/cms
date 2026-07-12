<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {!! $headContent !!}
    @if(!empty($rssUrl))
        <link rel="alternate" type="application/rss+xml" title="{{ $site->name ?? 'RSS' }} Feed" href="{{ $rssUrl }}">
    @endif
    @if(!empty($fontPreloads))
        {!! $fontPreloads !!}
    @endif
    <style>
        {!! $designTokensCss ?? '' !!}
        {!! $gridCss !!}
        {!! $criticalCss !!}
        /* Skip link (F3 accessibility) */
        .skip-link{position:absolute;left:-9999px;top:auto;z-index:5000;background:var(--color-bg,#fff);color:var(--color-text,#1a1a1a);padding:0.5rem 1rem;border:1px solid var(--color-border,#e2e8f0);text-decoration:none}
        .skip-link:focus{left:8px;top:8px}
    </style>
    @if(!empty($cssFile))
        <link rel="preload" href="{{ $cssFile }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="{{ $cssFile }}"></noscript>
    @endif
    @if(!empty($customCss))
        <style>{!! $customCss !!}</style>
    @endif
    @if(!empty($site->settings['google_analytics_id']))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $site->settings['google_analytics_id'] }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $site->settings['google_analytics_id'] }}');</script>
    @endif
    {!! $hookHeadScripts ?? '' !!}
    {!! $headScripts ?? '' !!}
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>
    {!! $hookBodyOpen ?? '' !!}
    {!! $gridHtml !!}
    {!! $hookBodyClose ?? '' !!}
    {!! $bodyScripts ?? '' !!}
    <script>(function(){try{navigator.sendBeacon('{{ config('app.url') }}/api/v1/sites/{{ $site->id }}/t',new Blob([JSON.stringify({p:location.pathname,r:document.referrer||null})],{type:'text/plain'}));}catch(e){}})();</script>
</body>
</html>
