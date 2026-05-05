<article style="padding:2rem 3rem;max-width:700px;background:var(--semantic-color-background-canvas,#fff);">
    <header style="margin-bottom:2rem;">
        <span style="display:inline-block;padding:0.25rem 0.75rem;background:var(--semantic-color-brand,#3b82f6);color:#fff;font-size:0.75rem;font-weight:600;border-radius:var(--semantic-size-radius-full,9999px);margin-bottom:0.75rem;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.brand,semantic.size.radius.full" data-theme-element="badge" data-theme-element-id="content-badge" @endif>
            Design
        </span>
        <h1 style="font-size:2.25rem;font-weight:700;line-height:1.2;margin-bottom:0.75rem;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.color.text.heading" data-theme-element="heading.h1" data-theme-element-id="content-h1" @endif>
            The Art of Thoughtful Design Systems
        </h1>
        <div style="display:flex;align-items:center;gap:0.75rem;color:var(--semantic-color-text-muted,#666);font-size:0.875rem;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.muted" data-theme-element="meta" data-theme-element-id="content-meta" @endif>
            <span>April 23, 2026</span> · <span>8 min read</span>
        </div>
    </header>

    <div style="font-size:1.05rem;line-height:1.8;color:var(--semantic-color-text-body,#333);"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.body,semantic.font.family.body" data-theme-element="article.body" data-theme-element-id="content-body" @endif>
        <p style="margin-bottom:1.25rem;">Design systems are living documents that evolve alongside the products they serve. They provide a shared language between designers and developers, ensuring consistency while enabling creativity.</p>

        <h2 style="font-size:1.5rem;font-weight:700;margin:2rem 0 0.75rem;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.font.family.display,semantic.color.text.heading" data-theme-element="heading.h2" data-theme-element-id="content-h2" @endif>
            Why Tokens Matter
        </h2>

        <p style="margin-bottom:1.25rem;">Design tokens are the atoms of a design system — the smallest, indivisible design decisions expressed as key-value pairs. A color, a spacing value, a font weight — each token captures a single decision.</p>

        <div style="background:var(--semantic-color-background-surface,#f5f5f5);border-left:3px solid var(--semantic-color-brand,#3b82f6);padding:1.25rem 1.5rem;border-radius:0 var(--semantic-size-radius-md,0.375rem) var(--semantic-size-radius-md,0.375rem) 0;margin:1.5rem 0;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.surface,semantic.color.brand,semantic.size.radius.md" data-theme-element="callout" data-theme-element-id="content-callout" @endif>
            <p style="font-style:italic;margin:0;">Good design is as little design as possible. Less, but better — because it concentrates on the essential aspects.</p>
        </div>

        <p style="margin-bottom:1.25rem;">When tokens are the source of truth, theme changes cascade automatically. Change the brand color once, and every button, link, and accent updates in perfect harmony.</p>

        <div style="display:flex;gap:1rem;margin:2rem 0;">
            <div style="flex:1;padding:1rem;background:var(--semantic-color-background-raised,#fff);border:1px solid var(--semantic-color-border-default,#e5e7eb);border-radius:var(--semantic-size-radius-md,0.375rem);text-align:center;"
                @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.raised,semantic.color.border.default,semantic.size.radius.md" data-theme-element="stat.card" data-theme-element-id="content-stat-1" @endif>
                <div style="font-size:2rem;font-weight:700;color:var(--semantic-color-brand,#3b82f6);"
                    @if($themeStudio ?? false) data-theme-tokens="semantic.color.brand" data-theme-element="stat.number" data-theme-element-id="content-num-1" @endif>63</div>
                <div style="font-size:0.75rem;color:var(--semantic-color-text-muted,#666);">Design Tokens</div>
            </div>
            <div style="flex:1;padding:1rem;background:var(--semantic-color-background-raised,#fff);border:1px solid var(--semantic-color-border-default,#e5e7eb);border-radius:var(--semantic-size-radius-md,0.375rem);text-align:center;">
                <div style="font-size:2rem;font-weight:700;color:var(--semantic-color-success,#22c55e);"
                    @if($themeStudio ?? false) data-theme-tokens="semantic.color.success" data-theme-element="stat.number" data-theme-element-id="content-num-2" @endif>3</div>
                <div style="font-size:0.75rem;color:var(--semantic-color-text-muted,#666);">Theme Tiers</div>
            </div>
        </div>
    </div>
</article>
