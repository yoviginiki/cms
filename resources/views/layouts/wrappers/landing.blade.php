{{-- Landing: conversion-focused with minimal chrome --}}
@if($layout->config['showHeader'] ?? true)
<header style="display:flex;align-items:center;justify-content:space-between;padding:1rem 2rem;position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(255,255,255,0.95);backdrop-filter:blur(10px);border-bottom:1px solid rgba(0,0,0,0.05);">
    <a href="/" style="font-weight:700;font-size:1.125rem;text-decoration:none;color:inherit;">{{ $site->name ?? 'Site' }}</a>
    @if(!empty($layout->config['headerCta']))
    <a href="{{ $layout->config['headerCta']['href'] ?? '#' }}" style="padding:0.5rem 1.25rem;background:var(--semantic-color-brand,#3b82f6);color:#fff;border-radius:0.375rem;text-decoration:none;font-weight:600;font-size:0.875rem;">
        {{ $layout->config['headerCta']['label'] ?? 'Get Started' }}
    </a>
    @endif
</header>
<div style="height:60px;"></div>
@endif

<main id="main-content">
    {!! $blocksHtml !!}
</main>

<footer style="padding:2rem;text-align:center;border-top:1px solid rgba(0,0,0,0.08);font-size:0.75rem;color:#9ca3af;">
    <span>© {{ date('Y') }} {{ $site->name ?? '' }}</span>
</footer>
