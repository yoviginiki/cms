<section style="background:var(--semantic-color-background-inverse,#111);color:var(--semantic-color-text-inverse,#fff);padding:4rem 2rem;text-align:center;"
    @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.inverse,semantic.color.text.inverse" data-theme-element="hero.section" data-theme-element-id="hero-1" @endif>
    <h1 style="font-size:var(--semantic-font-size-3xl,3rem);font-weight:700;margin-bottom:1rem;font-family:var(--semantic-font-family-display,serif);color:var(--semantic-color-text-h1,var(--semantic-color-text-inverse,#fff));"
        @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.font.size.3xl,semantic.color.text.h1,semantic.color.text.inverse" data-theme-element="hero.title" data-theme-element-id="hero-title" @endif>
        Build Something Beautiful
    </h1>
    <p style="font-size:1.25rem;opacity:0.8;max-width:600px;margin:0 auto 2rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.inverse" data-theme-element="hero.subtitle" data-theme-element-id="hero-sub" @endif>
        A complete platform for creating, managing, and publishing content that matters.
    </p>
    <button style="background:var(--semantic-color-brand,#3b82f6);color:#fff;padding:0.75rem 2rem;border:none;border-radius:var(--semantic-size-radius-md,0.375rem);font-size:1rem;font-weight:600;cursor:pointer;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.brand,semantic.size.radius.md" data-theme-element="button.primary" data-theme-element-id="hero-cta" @endif>
        Get Started
    </button>
</section>

<section style="background:var(--semantic-color-background-canvas,#fff);padding:4rem 2rem;text-align:center;"
    @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.canvas" data-theme-element="hero.light" data-theme-element-id="hero-2" @endif>
    <h2 style="font-size:var(--semantic-font-size-2xl,2.25rem);font-weight:700;margin-bottom:0.5rem;font-family:var(--semantic-font-family-display,inherit);color:var(--semantic-color-text-h2,var(--semantic-color-text-heading,#111));"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.h2,semantic.color.text.heading,semantic.font.family.display,semantic.font.size.2xl" data-theme-element="heading.h2" data-theme-element-id="hero-h2" @endif>
        Light Hero Variant
    </h2>
    <p style="color:var(--semantic-color-text-muted,#666);max-width:500px;margin:0 auto 1.5rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.muted" data-theme-element="text.muted" data-theme-element-id="hero-muted" @endif>
        Clean and minimal, perfect for content-focused pages.
    </p>
    <button style="background:transparent;border:2px solid var(--semantic-color-brand,#3b82f6);color:var(--semantic-color-brand,#3b82f6);padding:0.625rem 1.5rem;border-radius:var(--semantic-size-radius-md,0.375rem);font-weight:600;cursor:pointer;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.brand,semantic.size.radius.md" data-theme-element="button.outline" data-theme-element-id="hero-outline" @endif>
        Learn More
    </button>
</section>
