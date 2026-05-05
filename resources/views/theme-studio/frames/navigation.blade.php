{{-- Header --}}
<header style="display:flex;align-items:center;justify-content:space-between;padding:1rem 2rem;background:var(--semantic-color-background-raised,#fff);border-bottom:1px solid var(--semantic-color-border-subtle,#f1f5f9);"
    @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.raised,semantic.color.border.subtle" data-theme-element="header" data-theme-element-id="nav-header" @endif>
    <span style="font-family:var(--semantic-font-family-display,serif);font-size:1.25rem;font-weight:700;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.color.text.heading" data-theme-element="logo" data-theme-element-id="nav-logo" @endif>
        Brand Name
    </span>
    <nav style="display:flex;gap:1.5rem;">
        @foreach(['Home','About','Blog','Contact'] as $i => $item)
        <a href="#" style="text-decoration:none;color:var(--semantic-color-text-body,#333);font-size:0.875rem;font-weight:500;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.body" data-theme-element="nav.link" data-theme-element-id="nav-link-{{ $i }}" @endif>
            {{ $item }}
        </a>
        @endforeach
    </nav>
</header>

{{-- Dark header variant --}}
<header style="display:flex;align-items:center;justify-content:space-between;padding:1rem 2rem;background:var(--semantic-color-background-inverse,#111);margin-top:2rem;"
    @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.inverse" data-theme-element="header.dark" data-theme-element-id="nav-dark" @endif>
    <span style="font-family:var(--semantic-font-family-display,serif);font-size:1.25rem;font-weight:700;color:var(--semantic-color-text-inverse,#fff);"
        @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.color.text.inverse" data-theme-element="logo.inverse" data-theme-element-id="nav-logo-dark" @endif>
        Brand Name
    </span>
    <nav style="display:flex;gap:1.5rem;">
        @foreach(['Home','About','Blog','Contact'] as $i => $item)
        <a href="#" style="text-decoration:none;color:var(--semantic-color-text-inverse,#fff);font-size:0.875rem;font-weight:500;opacity:0.8;">{{ $item }}</a>
        @endforeach
    </nav>
</header>

{{-- Footer --}}
<footer style="padding:2rem;background:var(--semantic-color-background-inverse,#111);color:var(--semantic-color-text-inverse,#fff);margin-top:2rem;text-align:center;"
    @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.inverse,semantic.color.text.inverse" data-theme-element="footer" data-theme-element-id="nav-footer" @endif>
    <p style="font-size:0.875rem;opacity:0.6;">© 2026 Brand Name. All rights reserved.</p>
</footer>
