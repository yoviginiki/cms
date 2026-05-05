<div style="padding:2rem 3rem;max-width:700px;background:var(--semantic-color-background-canvas,#fff);">
    <h1 style="font-size:2.5rem;font-weight:700;margin-bottom:0.5rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.font.size.3xl,semantic.color.text.heading" data-theme-element="heading.h1" data-theme-element-id="typo-h1" @endif>
        Heading One — Display
    </h1>
    <h2 style="font-size:1.875rem;font-weight:600;margin-bottom:0.5rem;margin-top:1.5rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.font.size.2xl,semantic.color.text.heading" data-theme-element="heading.h2" data-theme-element-id="typo-h2" @endif>
        Heading Two — Section Title
    </h2>
    <h3 style="font-size:1.5rem;font-weight:600;margin-bottom:0.5rem;margin-top:1.5rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.color.text.heading" data-theme-element="heading.h3" data-theme-element-id="typo-h3" @endif>
        Heading Three — Subsection
    </h3>

    <p style="margin-top:1.5rem;font-size:1rem;line-height:1.7;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.body,semantic.font.size.base,semantic.color.text.body" data-theme-element="paragraph" data-theme-element-id="typo-p1" @endif>
        Body text using the theme's body font at base size. This paragraph shows how regular content reads with the current typography settings — line height, letter spacing, and color all working together.
    </p>

    <p style="margin-top:1rem;color:var(--semantic-color-text-muted,#666);font-size:0.875rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.muted,semantic.font.size.sm" data-theme-element="text.muted" data-theme-element-id="typo-muted" @endif>
        Muted text for secondary information — dates, metadata, captions, helper text.
    </p>

    <blockquote style="margin:1.5rem 0;padding:1rem 1.5rem;border-left:4px solid var(--semantic-color-brand,#3b82f6);font-style:italic;color:var(--semantic-color-text-body,#333);"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.brand,semantic.color.text.body" data-theme-element="blockquote" data-theme-element-id="typo-quote" @endif>
        "Design is not just what it looks like and feels like. Design is how it works."
        <cite style="display:block;font-style:normal;font-weight:600;margin-top:0.5rem;font-size:0.875rem;">— Steve Jobs</cite>
    </blockquote>

    <ul style="margin:1.5rem 0;padding-left:1.5rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.body" data-theme-element="list" data-theme-element-id="typo-list" @endif>
        <li style="margin-bottom:0.5rem;">First list item with body text color</li>
        <li style="margin-bottom:0.5rem;">Second item showing line spacing</li>
        <li>Third item — lists inherit body font</li>
    </ul>

    <p style="margin-top:1.5rem;">
        Inline code uses the <code style="font-family:var(--semantic-font-family-mono,monospace);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.375rem;border-radius:var(--semantic-size-radius-sm,0.25rem);font-size:0.875em;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.mono,semantic.color.background.surface,semantic.size.radius.sm" data-theme-element="code.inline" data-theme-element-id="typo-code" @endif>mono font family</code> token.
    </p>

    <a href="#" style="color:var(--semantic-color-text-link,#3b82f6);font-weight:500;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.link" data-theme-element="link" data-theme-element-id="typo-link" @endif>
        This is a link using the link color token →
    </a>
</div>
