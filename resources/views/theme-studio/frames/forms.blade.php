<div style="padding:2rem 3rem;max-width:500px;background:var(--semantic-color-background-canvas,#fff);">
    <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:1.5rem;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.heading,semantic.font.family.display" data-theme-element="heading.h2" data-theme-element-id="form-h2" @endif>
        Contact Form
    </h2>

    <div style="margin-bottom:1rem;">
        <label style="display:block;font-size:0.875rem;font-weight:500;margin-bottom:0.375rem;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.text.body" data-theme-element="form.label" data-theme-element-id="form-label-1" @endif>Name</label>
        <input type="text" placeholder="Your name" style="width:100%;padding:0.625rem 0.75rem;border:1px solid var(--semantic-color-border-default,#d1d5db);border-radius:var(--semantic-size-radius-md,0.375rem);font-size:0.875rem;font-family:inherit;background:var(--semantic-color-background-raised,#fff);"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.border.default,semantic.size.radius.md,semantic.color.background.raised" data-theme-element="input.text" data-theme-element-id="form-input-1" @endif>
    </div>

    <div style="margin-bottom:1rem;">
        <label style="display:block;font-size:0.875rem;font-weight:500;margin-bottom:0.375rem;">Email</label>
        <input type="email" placeholder="you@example.com" style="width:100%;padding:0.625rem 0.75rem;border:1px solid var(--semantic-color-border-default,#d1d5db);border-radius:var(--semantic-size-radius-md,0.375rem);font-size:0.875rem;font-family:inherit;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.border.default,semantic.size.radius.md" data-theme-element="input.email" data-theme-element-id="form-input-2" @endif>
    </div>

    <div style="margin-bottom:1.5rem;">
        <label style="display:block;font-size:0.875rem;font-weight:500;margin-bottom:0.375rem;">Message</label>
        <textarea rows="4" placeholder="Write your message..." style="width:100%;padding:0.625rem 0.75rem;border:1px solid var(--semantic-color-border-default,#d1d5db);border-radius:var(--semantic-size-radius-md,0.375rem);font-size:0.875rem;font-family:inherit;resize:vertical;"
            @if($themeStudio ?? false) data-theme-tokens="semantic.color.border.default,semantic.size.radius.md" data-theme-element="input.textarea" data-theme-element-id="form-textarea" @endif></textarea>
    </div>

    <button style="width:100%;padding:0.75rem;background:var(--semantic-color-brand,#3b82f6);color:#fff;border:none;border-radius:var(--semantic-size-radius-md,0.375rem);font-weight:600;font-size:1rem;cursor:pointer;"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.brand,semantic.size.radius.md" data-theme-element="button.primary" data-theme-element-id="form-submit" @endif>
        Send Message
    </button>

    <div style="margin-top:1.5rem;padding:1rem;background:var(--semantic-color-background-surface,#f5f5f5);border-radius:var(--semantic-size-radius-md,0.375rem);"
        @if($themeStudio ?? false) data-theme-tokens="semantic.color.background.surface,semantic.size.radius.md" data-theme-element="surface" data-theme-element-id="form-notice" @endif>
        <p style="font-size:0.8rem;color:var(--semantic-color-text-muted,#666);">
            <span style="color:var(--semantic-color-success,#22c55e);font-weight:600;"
                @if($themeStudio ?? false) data-theme-tokens="semantic.color.success" data-theme-element="status.success" data-theme-element-id="form-success" @endif>✓ Success</span>
            &nbsp;·&nbsp;
            <span style="color:var(--semantic-color-warning,#eab308);font-weight:600;"
                @if($themeStudio ?? false) data-theme-tokens="semantic.color.warning" data-theme-element="status.warning" data-theme-element-id="form-warning" @endif>⚠ Warning</span>
            &nbsp;·&nbsp;
            <span style="color:var(--semantic-color-danger,#ef4444);font-weight:600;"
                @if($themeStudio ?? false) data-theme-tokens="semantic.color.danger" data-theme-element="status.danger" data-theme-element-id="form-danger" @endif>✗ Error</span>
        </p>
    </div>
</div>
