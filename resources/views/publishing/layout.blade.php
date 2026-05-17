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
    @if(!empty($designTokensCss))
        <style>{!! $designTokensCss !!}</style>
    @endif
    @if(!empty($criticalCss))
        <style>{!! $criticalCss !!}</style>
    @endif
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
    {!! $headScripts ?? '' !!}
    <style>
    /* ─── Block overlay z-index fix ─── */
    /* Ensure block content stacks above background overlays */
    .block-bg-overlay ~ * { position: relative; z-index: 1; }

    /* ─── Override old theme nav styles ─── */
    nav.nav-menu-container{all:unset !important}
    nav .nav-menu{all:unset !important}

    /* ─── Responsive Navigation ─── */
    .site-nav{position:sticky !important;top:0;z-index:1000;background:rgba(255,255,255,0.88);backdrop-filter:saturate(180%) blur(20px);-webkit-backdrop-filter:saturate(180%) blur(20px);border-bottom:1px solid var(--color-border-light, #f0f0eb)}
    .site-nav .nav-inner{display:flex !important;align-items:center;justify-content:space-between;max-width:var(--container-width, 1400px);margin:0 auto;padding:0 var(--container-padding, 40px);height:56px}
    .site-nav .nav-logo{font-family:var(--font-heading, Georgia, serif);font-size:18px;font-weight:400;color:var(--color-text, #1a1a1a);text-decoration:none;letter-spacing:-0.02em;flex-shrink:0}
    .site-nav .nav-menu{display:flex !important;align-items:center;gap:24px;list-style:none !important;margin:0 !important;padding:0 !important;height:auto !important}
    .site-nav .nav-menu li{list-style:none;margin:0;padding:0}
    .site-nav .nav-menu a{font-family:var(--font-body, sans-serif);font-size:13px;color:var(--color-text, #1a1a1a);text-decoration:none;opacity:0.6;transition:opacity 0.3s;letter-spacing:0.04em;text-transform:none}
    .site-nav .nav-menu a:hover{opacity:1}
    .site-nav .has-children{position:relative}
    .site-nav .nav-submenu{display:none;position:absolute;top:calc(100% + 8px);left:-16px;min-width:180px;padding:8px 0;background:#fff;border:1px solid var(--color-border-light, #eee);box-shadow:0 8px 32px rgba(0,0,0,0.08);list-style:none !important;z-index:100;border-radius:8px}
    .site-nav .has-children:hover .nav-submenu{display:block}
    .site-nav .nav-submenu li{list-style:none}
    .site-nav .nav-submenu a{display:block;padding:8px 20px;font-size:13px;white-space:nowrap;opacity:0.7}
    .site-nav .nav-submenu a:hover{background:var(--color-bg-alt, #f5f5f0);opacity:1}

    /* Hamburger */
    .site-nav .nav-toggle{display:none;background:none;border:none;cursor:pointer;padding:8px;width:36px;height:36px;flex-direction:column;justify-content:center;gap:5px;align-items:center}
    .site-nav .nav-toggle-line{display:block;width:20px;height:1.5px;background:var(--color-text, #1a1a1a);transition:transform 0.3s, opacity 0.3s;border-radius:1px}
    .site-nav.nav-open .nav-toggle-line:nth-child(1){transform:translateY(6.5px) rotate(45deg)}
    .site-nav.nav-open .nav-toggle-line:nth-child(2){opacity:0}
    .site-nav.nav-open .nav-toggle-line:nth-child(3){transform:translateY(-6.5px) rotate(-45deg)}

    /* Mobile */
    @media(max-width:768px){
        .site-nav .nav-toggle{display:flex !important}
        .site-nav .nav-menu{display:none !important;position:absolute;top:56px;left:0;right:0;flex-direction:column;background:rgba(255,255,255,0.97);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-bottom:1px solid var(--color-border-light, #eee);padding:16px 0;gap:0 !important;box-shadow:0 8px 32px rgba(0,0,0,0.06)}
        .site-nav.nav-open .nav-menu{display:flex !important}
        .site-nav .nav-menu li{width:100%}
        .site-nav .nav-menu a{display:block;padding:14px var(--container-padding, 24px);font-size:15px;opacity:0.7;border-bottom:1px solid var(--color-border-light, #f0f0eb)}
        .site-nav .nav-menu a:hover{opacity:1;background:var(--color-bg-alt, #f5f5f0)}
        .site-nav .nav-submenu{position:static !important;box-shadow:none !important;border:none !important;padding:0 !important;background:transparent !important;border-radius:0 !important}
        .site-nav .nav-submenu a{padding-left:calc(var(--container-padding, 24px) + 16px);font-size:14px}
        .site-nav .has-children:hover .nav-submenu,.site-nav .nav-submenu{display:block !important}
        .site-nav .nav-inner{position:relative;padding:0 20px}
    }

    /* Dark mode */
    @media(prefers-color-scheme:dark){
        .site-nav{background:rgba(10,10,10,0.88) !important;border-bottom-color:rgba(255,255,255,0.06) !important}
        .site-nav .nav-logo{color:#f5f5f7}
        .site-nav .nav-menu a{color:#f5f5f7}
        .site-nav .nav-toggle-line{background:#f5f5f7}
        .site-nav .nav-submenu{background:#1d1d1f !important;border-color:rgba(255,255,255,0.08) !important}
        .site-nav .nav-submenu a:hover{background:rgba(255,255,255,0.06) !important}
        @media(max-width:768px){
            .site-nav .nav-menu{background:rgba(10,10,10,0.97) !important}
            .site-nav .nav-menu a{border-bottom-color:rgba(255,255,255,0.06) !important}
        }
    }
    </style>
</head>
<body>
    @if(!empty($navigation))
    <header role="banner">
        {!! $navigation !!}
    </header>
    @endif
    <main role="main"@if(!empty($mainStyle)) style="{{ $mainStyle }}"@endif>
        {!! $renderedBlocks !!}
    </main>
    @if(!empty($footerNavigation))
    <footer role="contentinfo">
        {!! $footerNavigation !!}
    </footer>
    @endif
    {!! $bodyScripts ?? '' !!}
</body>
</html>
