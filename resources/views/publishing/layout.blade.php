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
    .block-bg-overlay ~ * { position: relative; z-index: 1; }

    /* ─── Mobile responsive — published pages ─── */
    /* Prevent horizontal overflow on all screen sizes */
    html,body{overflow-x:hidden;max-width:100vw}

    @media (max-width: 767px) {
      /* Force multi-column grids to single column */
      [style*="grid-template-columns:repeat(2"] { grid-template-columns: 1fr !important; }
      [style*="grid-template-columns:repeat(3"] { grid-template-columns: 1fr !important; }
      [style*="grid-template-columns:repeat(4"] { grid-template-columns: 1fr !important; }
      [style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
      [style*="grid-template-columns: 1fr 1fr"] { grid-template-columns: 1fr !important; }

      /* Reduce section padding on mobile */
      .section-block { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }

      /* Cap section inner max-width — no extra padding if already inside a section */
      .section-block > div { padding-left: 0 !important; padding-right: 0 !important; }

      /* Nested sections (e.g. post content inside latestposts) — no double padding */
      .section-block .section-block { padding-left: 0 !important; padding-right: 0 !important; }

      /* Ensure images don't overflow */
      img { max-width: 100% !important; height: auto !important; }

      /* Fix sticky sidebar on mobile */
      .stickysidebar-block > div { flex-direction: column !important; }
      .stickysidebar-block aside { width: 100% !important; position: static !important; }

      /* Tables scroll horizontally */
      table { display: block; overflow-x: auto; }

      /* Reduce large headings */
      h1 { font-size: clamp(1.5rem, 5vw, 3rem) !important; }
      h2 { font-size: clamp(1.25rem, 4vw, 2rem) !important; }

      /* Fix fullbleed on mobile */
      .fullbleed-block section { min-height: 40vh !important; }
    }

    @media (max-width: 480px) {
      /* Even smaller phones */
      .section-block { padding-top: 2rem !important; padding-bottom: 2rem !important; }
      body { font-size: 15px; }
    }

    /* ─── Override old theme nav styles ─── */
    nav.nav-menu-container{all:unset !important}
    nav .nav-menu{all:unset !important}

    /* ─── Responsive Navigation ─── */
    .site-nav{position:sticky !important;top:0;z-index:1000;background:color-mix(in srgb, var(--color-bg,#fff) 92%, transparent);backdrop-filter:saturate(180%) blur(var(--nav-bg-blur,20px));-webkit-backdrop-filter:saturate(180%) blur(var(--nav-bg-blur,20px));border-bottom:1px solid var(--color-border-light, #f0f0eb)}
    .site-nav .nav-inner{display:flex !important;align-items:center;justify-content:space-between;max-width:var(--container-width, 1200px);width:90%;margin:0 auto;padding:var(--nav-padding, 14px 0);height:auto}
    .site-nav .nav-logo{font-family:var(--font-heading, sans-serif);font-size:var(--nav-logo-size, 14px);font-weight:var(--nav-logo-weight, 600);color:var(--color-text, #1a1a1a);text-decoration:none;letter-spacing:var(--nav-logo-tracking, 0.1em);text-transform:var(--nav-logo-transform, none);flex-shrink:0}
    .site-nav .nav-menu{display:flex !important;align-items:center;gap:var(--nav-gap, 28px);list-style:none !important;margin:0 !important;padding:0 !important;height:auto !important}
    .site-nav .nav-menu li{list-style:none;margin:0;padding:0}
    .site-nav .nav-menu a{font-family:var(--font-nav, var(--font-heading, sans-serif));font-size:var(--nav-font-size, 12px);font-weight:var(--nav-font-weight, 500);color:var(--color-text-muted, #666);text-decoration:none;transition:color 0.2s;letter-spacing:var(--nav-tracking, 0.12em);text-transform:var(--nav-transform, uppercase)}
    .site-nav .nav-menu a:hover{color:var(--color-primary, #3b82f6)}
    .site-nav .has-children{position:relative}
    .site-nav .nav-submenu{display:none;position:absolute;top:calc(100% + 8px);left:-16px;min-width:180px;padding:8px 0;background:var(--color-bg,#fff);border:1px solid var(--color-border-light, #eee);box-shadow:var(--shadow-md,0 8px 32px rgba(0,0,0,0.08));list-style:none !important;z-index:100;border-radius:var(--border-radius-md,0.5rem)}
    .site-nav .has-children:hover .nav-submenu{display:block}
    .site-nav .nav-submenu li{list-style:none}
    .site-nav .nav-submenu a{display:block;padding:8px 20px;font-size:var(--nav-font-size,12px);white-space:nowrap;color:var(--color-text-muted,#666);transition:color 0.2s}
    .site-nav .nav-submenu a:hover{background:var(--color-bg-alt, #f5f5f0);opacity:1}

    /* Hamburger */
    .site-nav .nav-toggle{display:none;background:none;border:none;cursor:pointer;padding:8px;width:36px;height:36px;flex-direction:column;justify-content:center;gap:5px;align-items:center}
    .site-nav .nav-toggle-line{display:block;width:20px;height:1.5px;background:var(--color-text, #1a1a1a);transition:transform 0.3s, opacity 0.3s;border-radius:1px}
    .site-nav.nav-open .nav-toggle-line:nth-child(1){transform:translateY(6.5px) rotate(45deg)}
    .site-nav.nav-open .nav-toggle-line:nth-child(2){opacity:0}
    .site-nav.nav-open .nav-toggle-line:nth-child(3){transform:translateY(-6.5px) rotate(-45deg)}

    /* ─── Overlay Nav Mode ─── */
    /* Targets both layout nav (.nav-toggle/.nav-menu) and MenuRenderer nav (.menu-hamburger/.menu-desktop) */
    .site-nav--overlay .nav-toggle,.site-nav--overlay .menu-hamburger{display:flex !important}
    .site-nav--overlay .nav-menu,.site-nav--overlay .menu-desktop{display:none !important;position:fixed;top:0;left:0;right:0;bottom:0;flex-direction:column;align-items:center;justify-content:center;gap:var(--nav-overlay-gap, 1.5rem) !important;background:var(--nav-overlay-bg, rgba(69,64,48,0.8));backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);z-index:2999;padding:2rem !important;list-style:none;overflow-y:auto}
    .site-nav--overlay.nav-open .nav-menu,.site-nav--overlay.menu-open .menu-desktop{display:flex !important}
    .site-nav--overlay .nav-menu li,.site-nav--overlay .menu-desktop li{width:auto;list-style:none}
    .site-nav--overlay .nav-menu a,.site-nav--overlay .menu-desktop a{display:block;padding:0.5rem 1rem;font-size:var(--nav-overlay-font-size, 2rem) !important;font-weight:var(--nav-overlay-font-weight, 300) !important;color:var(--nav-overlay-color, #F3F0EA) !important;letter-spacing:var(--nav-overlay-tracking, 0.05em) !important;text-transform:var(--nav-overlay-transform, none) !important;border-bottom:none !important;opacity:0.85;transition:opacity 0.3s}
    .site-nav--overlay .nav-menu a:hover,.site-nav--overlay .menu-desktop a:hover{opacity:1;background:transparent !important;color:var(--nav-overlay-hover-color, #ffffff) !important}
    .site-nav--overlay.nav-open .nav-toggle,.site-nav--overlay.menu-open .menu-hamburger{position:fixed;top:20px;right:20px;z-index:3001}
    .site-nav--overlay.nav-open .nav-toggle-line,.site-nav--overlay.menu-open .menu-hamburger span{background:var(--nav-overlay-color, #F3F0EA)}
    .site-nav--overlay .nav-submenu,.site-nav--overlay .submenu{position:static !important;box-shadow:none !important;border:none !important;padding:0 !important;background:transparent !important;border-radius:0 !important;display:block !important}
    .site-nav--overlay .nav-submenu a,.site-nav--overlay .submenu a{font-size:var(--nav-overlay-sub-font-size, 1.2rem) !important;color:var(--nav-overlay-color, #F3F0EA) !important;opacity:0.6}
    /* Hide mobile hamburger panel in overlay mode — use full-screen overlay instead */
    .site-nav--overlay .menu-hamburger-panel{display:none !important}
    @media(max-width:768px){
        .site-nav--overlay .nav-menu a,.site-nav--overlay .menu-desktop a{font-size:1.4rem !important;padding:0.4rem 1rem}
        .site-nav--overlay .nav-submenu a,.site-nav--overlay .submenu a{font-size:1rem !important}
    }

    /* Mobile — dropdown mode (default, non-overlay) */
    @media(max-width:768px){
        .site-nav:not(.site-nav--overlay) .nav-toggle{display:flex !important}
        .site-nav:not(.site-nav--overlay) .nav-menu{display:none !important;position:absolute;top:56px;left:0;right:0;flex-direction:column;background:color-mix(in srgb, var(--color-bg,#fff) 97%, transparent);backdrop-filter:blur(var(--nav-bg-blur,20px));-webkit-backdrop-filter:blur(var(--nav-bg-blur,20px));border-bottom:1px solid var(--color-border-light, #eee);padding:16px 0;gap:0 !important;box-shadow:0 8px 32px rgba(0,0,0,0.06)}
        .site-nav:not(.site-nav--overlay).nav-open .nav-menu{display:flex !important}
        .site-nav:not(.site-nav--overlay) .nav-menu li{width:100%}
        .site-nav:not(.site-nav--overlay) .nav-menu a{display:block;padding:14px var(--container-padding, 24px);font-size:15px;opacity:0.7;border-bottom:1px solid var(--color-border-light, #f0f0eb)}
        .site-nav:not(.site-nav--overlay) .nav-menu a:hover{opacity:1;background:var(--color-bg-alt, #f5f5f0)}
        .site-nav:not(.site-nav--overlay) .nav-submenu{position:static !important;box-shadow:none !important;border:none !important;padding:0 !important;background:transparent !important;border-radius:0 !important}
        .site-nav:not(.site-nav--overlay) .nav-submenu a{padding-left:calc(var(--container-padding, 24px) + 16px);font-size:14px}
        .site-nav:not(.site-nav--overlay) .has-children:hover .nav-submenu,.site-nav:not(.site-nav--overlay) .nav-submenu{display:block !important}
        .site-nav:not(.site-nav--overlay) .nav-inner{position:relative;padding:0 20px}
    }

    /* ─── Footer ─── */
    footer[role="contentinfo"]{background:var(--footer-bg, var(--color-bg-alt, #f8fafc));color:var(--footer-color, var(--color-text-muted, #64748b));border-top:1px solid var(--footer-border-color, var(--color-border-light, #f0f0eb));padding:2rem 0}
    footer[role="contentinfo"] a{color:var(--footer-color, var(--color-text-muted, #64748b));transition:color 0.2s}
    footer[role="contentinfo"] a:hover{color:var(--color-primary, #3b82f6);opacity:1}

    /* In-text links are underlined — color alone doesn't distinguish them
       (WCAG 1.4.1 / Lighthouse link-in-text-block). Menus/buttons opt out. */
    .post-meta a,main p a:not(.btn):not([class*="button"]),main li a:not(.btn){text-decoration:underline;text-underline-offset:0.15em}

    /* Skip link (F3 accessibility) */
    .skip-link{position:absolute;left:-9999px;top:auto;z-index:5000;background:var(--color-bg,#fff);color:var(--color-text,#1a1a1a);padding:0.5rem 1rem;border:1px solid var(--color-border,#e2e8f0);text-decoration:none}
    .skip-link:focus{left:8px;top:8px}

    /* Dark mode — nav inherits from theme tokens, no hardcoded overrides */
    </style>
</head>
@php
    $__isHome = !empty($content) && !empty($site) && ($site->settings['homepage_id'] ?? null) === ($content->id ?? null);
    // Layout wrappers ship their own <main> — never nest a second one (F3).
    $__bodyHasMain = str_contains($renderedBlocks ?? '', '<main');
@endphp
<body{!! $__isHome ? ' data-page="home"' : '' !!}>
    <a class="skip-link" href="#main-content">Skip to content</a>
    @if(!empty($navigation))
    <header role="banner">
        {!! $navigation !!}
    </header>
    @endif
    @if($__bodyHasMain)
    {!! $renderedBlocks !!}
    @else
    <main id="main-content" role="main"@if(!empty($mainStyle)) style="{{ $mainStyle }}"@endif>
        {!! $renderedBlocks !!}
    </main>
    @endif
    @if(!empty($footerNavigation))
    <footer role="contentinfo">
        {!! $footerNavigation !!}
    </footer>
    @endif
    {!! $bodyScripts ?? '' !!}
    @if(!empty($site))
    <script>
    (function(){try{navigator.sendBeacon('{{ config('app.url') }}/api/v1/sites/{{ $site->id }}/t',new Blob([JSON.stringify({p:location.pathname,r:document.referrer||''})],{type:'text/plain'}));}catch(e){}})();
    </script>
    @endif
</body>
</html>
