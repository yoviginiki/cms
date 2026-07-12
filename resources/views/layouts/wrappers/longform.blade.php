{{-- Longform: reading-optimized with progress bar and sticky TOC --}}
<div id="longform-progress" style="position:fixed;top:0;left:0;height:3px;background:var(--semantic-color-brand,#3b82f6);z-index:200;transition:width 0.1s;width:0;"></div>

@include('layouts.partials.site-header')

<div style="display:flex;gap:2rem;max-width:65rem;margin:0 auto;padding:0 1.5rem;">
    <main id="main-content" style="flex:1;max-width:{{ $layout->supports['maxWidthValue'] ?? '40rem' }};margin:0 auto;min-width:0;">
        <article style="font-size:1.1rem;line-height:1.85;">
            {!! $blocksHtml !!}
        </article>
    </main>

    <aside id="longform-toc" style="width:220px;position:sticky;top:80px;align-self:flex-start;font-size:0.8rem;display:none;" class="longform-toc-sidebar">
        <h4 style="font-weight:600;margin-bottom:0.75rem;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--semantic-color-text-muted,#999);">Contents</h4>
        <nav id="longform-toc-nav"></nav>
    </aside>
</div>

@include('layouts.partials.site-footer')

<script>
// Reading progress bar
window.addEventListener('scroll', function() {
    var h = document.documentElement.scrollHeight - window.innerHeight;
    var p = h > 0 ? (window.scrollY / h) * 100 : 0;
    document.getElementById('longform-progress').style.width = p + '%';
}, { passive: true });

// Auto-generate TOC from h2/h3 headings
(function() {
    var headings = document.querySelectorAll('main h2, main h3');
    if (headings.length < 2) return;
    var nav = document.getElementById('longform-toc-nav');
    var sidebar = document.getElementById('longform-toc');
    if (!nav || !sidebar) return;
    sidebar.style.display = 'block';
    headings.forEach(function(h, i) {
        var id = h.id || ('section-' + i);
        h.id = id;
        var link = document.createElement('a');
        link.href = '#' + id;
        link.textContent = h.textContent;
        link.style.cssText = 'display:block;padding:0.25rem 0;color:var(--semantic-color-text-muted,#666);text-decoration:none;' + (h.tagName === 'H3' ? 'padding-left:0.75rem;font-size:0.75rem;' : '');
        nav.appendChild(link);
    });
    // Hide on mobile
    if (window.innerWidth < 900) sidebar.style.display = 'none';
})();
</script>
