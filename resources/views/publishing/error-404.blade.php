<!DOCTYPE html>
<html lang="{{ $lang ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | {{ $site->name }}</title>
    <meta name="robots" content="noindex">
    @if(!empty($criticalCss))<style>{!! $criticalCss !!}</style>@endif
    @if(!empty($customCss))<style>{!! $customCss !!}</style>@endif
</head>
<body>
    <header role="banner">@if(!empty($navigation)){!! $navigation !!}@endif</header>
    <main role="main" style="max-width: 600px; margin: 0 auto; padding: 4rem 1rem; text-align: center;">
        <h1 style="font-size: 6rem; font-weight: 700; color: #e5e7eb; line-height: 1;">404</h1>
        <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem;">Page Not Found</h2>
        <p style="color: #6b7280; margin-bottom: 2rem;">The page you're looking for doesn't exist or has been moved.</p>
        <a href="/" style="display: inline-block; padding: 0.625rem 1.5rem; background: var(--color-primary, #3b82f6); color: #fff; text-decoration: none; border-radius: 0.5rem; font-weight: 600;">Go Home</a>
    </main>
    <footer role="contentinfo">@if(!empty($footerNavigation)){!! $footerNavigation !!}@endif</footer>
</body>
</html>
