<div style="padding:2rem 3rem;max-width:700px;background:var(--semantic-color-background-canvas,#fff);">

    <div style="display:flex;align-items:baseline;gap:0.75rem;margin-bottom:0.5rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;">&lt;h1&gt;</span>
        <h1 style="font-size:var(--semantic-font-size-3xl,2.5rem);font-weight:700;margin:0;font-family:var(--semantic-font-family-display,inherit);color:var(--semantic-color-text-h1,var(--semantic-color-text-heading,#111));"
            @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.font.size.3xl,semantic.color.text.h1,semantic.color.text.heading" data-theme-element="heading.h1" data-theme-element-id="typo-h1" @endif>
            Heading One — Display
        </h1>
    </div>

    <div style="display:flex;align-items:baseline;gap:0.75rem;margin-top:1.5rem;margin-bottom:0.5rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;">&lt;h2&gt;</span>
        <h2 style="font-size:var(--semantic-font-size-2xl,1.875rem);font-weight:600;margin:0;font-family:var(--semantic-font-family-display,inherit);color:var(--semantic-color-text-h2,var(--semantic-color-text-heading,#111));"
            @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.font.size.2xl,semantic.color.text.h2,semantic.color.text.heading" data-theme-element="heading.h2" data-theme-element-id="typo-h2" @endif>
            Heading Two — Section Title
        </h2>
    </div>

    <div style="display:flex;align-items:baseline;gap:0.75rem;margin-top:1.5rem;margin-bottom:0.5rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;">&lt;h3&gt;</span>
        <h3 style="font-size:var(--semantic-font-size-xl,1.5rem);font-weight:600;margin:0;font-family:var(--semantic-font-family-display,inherit);color:var(--semantic-color-text-h3,var(--semantic-color-text-heading,#111));"
            @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.font.size.xl,semantic.color.text.h3,semantic.color.text.heading" data-theme-element="heading.h3" data-theme-element-id="typo-h3" @endif>
            Heading Three — Subsection
        </h3>
    </div>

    <div style="display:flex;align-items:baseline;gap:0.75rem;margin-top:1.5rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;">&lt;p&gt;</span>
        <p style="margin:0;font-size:var(--semantic-font-size-base,1rem);line-height:1.7;font-family:var(--semantic-font-family-body,system-ui);color:var(--semantic-color-text-body,#333);"
            @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.body,semantic.font.size.base,semantic.color.text.body" data-theme-element="paragraph" data-theme-element-id="typo-p1" @endif>
            Body text using the theme's body font at base size. This paragraph shows how regular content reads with the current typography settings — line height, letter spacing, and color all working together.
        </p>
    </div>

    <div style="display:flex;align-items:baseline;gap:0.75rem;margin-top:1rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;">&lt;p.muted&gt;</span>
        <p style="margin:0;color:var(--semantic-color-text-muted,#666);font-size:var(--semantic-font-size-sm,0.875rem);"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.muted,semantic.font.size.sm" data-theme-element="text.muted" data-theme-element-id="typo-muted" @endif>
            Muted text for secondary information — dates, metadata, captions, helper text.
        </p>
    </div>

    <div style="display:flex;align-items:flex-start;gap:0.75rem;margin-top:1.5rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;margin-top:1rem;">&lt;blockquote&gt;</span>
        <blockquote style="margin:0;padding:1rem 1.5rem;border-left:4px solid var(--semantic-color-brand,#3b82f6);font-style:italic;color:var(--semantic-color-text-body,#333);font-family:var(--semantic-font-family-body,system-ui);"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.brand,semantic.color.text.body" data-theme-element="blockquote" data-theme-element-id="typo-quote" @endif>
            "Design is not just what it looks like and feels like. Design is how it works."
            <cite style="display:block;font-style:normal;font-weight:600;margin-top:0.5rem;font-size:0.875rem;">— Steve Jobs</cite>
        </blockquote>
    </div>

    <div style="display:flex;align-items:flex-start;gap:0.75rem;margin-top:1.5rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;margin-top:0.25rem;">&lt;ul&gt; / &lt;li&gt;</span>
        <ul style="margin:0;padding-left:1.5rem;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.body" data-theme-element="list" data-theme-element-id="typo-list" @endif>
            <li style="margin-bottom:0.5rem;">First list item with body text color</li>
            <li style="margin-bottom:0.5rem;">Second item showing line spacing</li>
            <li>Third item — lists inherit body font</li>
        </ul>
    </div>

    <div style="display:flex;align-items:baseline;gap:0.75rem;margin-top:1.5rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;">&lt;code&gt;</span>
        <p style="margin:0;">
            Inline code uses the <code style="font-family:var(--semantic-font-family-mono,monospace);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.375rem;border-radius:var(--semantic-size-radius-sm,0.25rem);font-size:0.875em;"
                @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.mono,semantic.color.background.surface,semantic.size.radius.sm" data-theme-element="code.inline" data-theme-element-id="typo-code" @endif>mono font family</code> token.
        </p>
    </div>

    <div style="display:flex;align-items:baseline;gap:0.75rem;margin-top:1.5rem;">
        <span style="font-size:0.6875rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:var(--semantic-color-text-muted,#999);background:var(--semantic-color-background-surface,#f5f5f5);padding:0.125rem 0.5rem;border-radius:3px;flex-shrink:0;">&lt;a&gt;</span>
        <a href="#" style="font-weight:500;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.link,semantic.color.text.link.hover,semantic.text.decoration.link,semantic.text.decoration.link.hover" data-theme-element="link" data-theme-element-id="typo-link" @endif>
            This is a link using the link color token →
        </a>
    </div>
</div>
