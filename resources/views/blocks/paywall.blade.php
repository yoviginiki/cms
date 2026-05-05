@php
    $previewLines = (int) ($data['previewLines'] ?? 3);
    $blurIntensity = (int) ($data['blurIntensity'] ?? 8);
    $heading = $data['heading'] ?? 'Subscribe to continue reading';
    $ctaText = $data['ctaText'] ?? 'Subscribe';
    $ctaUrl = $data['ctaUrl'] ?? '#';
    $lineHeight = 1.6;
    $visibleHeight = $previewLines * $lineHeight;
@endphp
<div style="position:relative;overflow:hidden;">
    <div style="max-height:{{ $visibleHeight }}em;overflow:hidden;">
        {!! $children !!}
    </div>
    <div style="position:absolute;bottom:0;left:0;right:0;height:{{ $visibleHeight + 4 }}em;max-height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;padding:2rem 1rem;background:linear-gradient(to bottom, rgba(255,255,255,0) 0%, rgba(255,255,255,0.85) 40%, rgba(255,255,255,1) 100%);backdrop-filter:blur({{ $blurIntensity }}px);">
        <h3 style="font-size:1.25rem;font-weight:600;margin:0 0 1rem;text-align:center;">{{ $heading }}</h3>
        <a href="{{ $ctaUrl }}" style="display:inline-block;padding:0.625rem 2rem;background:#3b82f6;color:#fff;border-radius:0.375rem;font-size:0.875rem;font-weight:500;text-decoration:none;">{{ $ctaText }}</a>
    </div>
</div>
