<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
{{ $themeCss }}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: var(--semantic-font-family-body, system-ui, sans-serif);
    color: var(--semantic-color-text-body, #333);
    background: var(--semantic-color-background-canvas, #fff);
    line-height: 1.6;
}
h1, h2, h3, h4, h5, h6 {
    font-family: var(--semantic-font-family-display, inherit);
    color: var(--semantic-color-text-heading, #111);
    line-height: 1.3;
}
a { color: var(--semantic-color-text-link, #3b82f6); text-decoration: var(--semantic-text-decoration-link, none); transition: color 0.15s, text-decoration 0.15s; }
a:hover { color: var(--semantic-color-text-link-hover, #2563eb); text-decoration: var(--semantic-text-decoration-link-hover, underline); }
</style>
</head>
<body>
{!! $frameHtml !!}

@if($studio)
<script>
// Studio iframe bridge — send element interactions to parent
document.addEventListener('mouseover', function(e) {
    var el = e.target.closest('[data-theme-tokens]');
    if (!el) return;
    window.parent.postMessage({
        type: 'hover',
        element: {
            elementId: el.dataset.themeElementId || '',
            tokenPaths: (el.dataset.themeTokens || '').split(',').filter(Boolean),
            symbolicName: el.dataset.themeElement || '',
            rect: el.getBoundingClientRect(),
        }
    }, '*');
});
document.addEventListener('click', function(e) {
    var el = e.target.closest('[data-theme-tokens]');
    if (!el) { window.parent.postMessage({ type: 'click', element: null }, '*'); return; }
    e.preventDefault();
    e.stopPropagation();
    window.parent.postMessage({
        type: 'click',
        element: {
            elementId: el.dataset.themeElementId || '',
            tokenPaths: (el.dataset.themeTokens || '').split(',').filter(Boolean),
            symbolicName: el.dataset.themeElement || '',
            rect: el.getBoundingClientRect(),
        },
        shiftKey: e.shiftKey,
    }, '*');
}, true);
document.addEventListener('mouseout', function(e) {
    if (!e.target.closest('[data-theme-tokens]')) return;
    window.parent.postMessage({ type: 'hover', element: null }, '*');
});
// Listen for token updates from parent — validate origin
window.addEventListener('message', function(e) {
    if (e.origin !== window.location.origin) return;
    if (!e.data || !e.data.type) return;
    if (e.data.type === 'updateToken' && typeof e.data.path === 'string' && typeof e.data.value === 'string') {
        var safePath = e.data.path.replace(/[^a-zA-Z0-9.\-_]/g, '');
        var safeValue = e.data.value.replace(/[{}<>]/g, '');
        document.documentElement.style.setProperty('--' + safePath.replace(/\./g, '-'), safeValue);
    }
});
window.parent.postMessage({ type: 'ready' }, '*');
</script>
@endif
</body>
</html>
