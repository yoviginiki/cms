{{-- Cohesive one-page theme preview: nav → hero → card grid → type specimen →
     footer. Renders with the selected theme's REAL published CSS (served by
     ThemeEngineController::studioFrame via DesignTokenGenerator), so the picker
     preview matches what the live site will look like. Semantic tokens with
     safe fallbacks throughout. --}}
<div style="font-family:var(--semantic-font-family-body,system-ui,sans-serif);color:var(--semantic-color-text-body,#333);background:var(--semantic-color-background-canvas,#fff);min-height:100%;">

  {{-- nav --}}
  <nav style="display:flex;align-items:center;justify-content:space-between;padding:var(--semantic-nav-padding,16px 32px);border-bottom:1px solid var(--semantic-color-border-default,#e5e5e5);gap:24px;">
    <span style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-nav-logo-size,16px);font-weight:var(--semantic-nav-logo-weight,700);letter-spacing:var(--semantic-nav-logo-tracking,0.08em);text-transform:uppercase;color:var(--semantic-color-text-heading,#111);">Ensōdo</span>
    <span style="display:flex;gap:var(--semantic-nav-gap,28px);">
      @foreach(['Work','Journal','Studio','Contact'] as $n)
        <a href="#" style="font-family:var(--semantic-font-family-nav,var(--semantic-font-family-display,inherit));font-size:var(--semantic-nav-font-size,13px);font-weight:var(--semantic-nav-font-weight,600);letter-spacing:var(--semantic-nav-letter-spacing,0.12em);text-transform:var(--semantic-nav-text-transform,uppercase);color:var(--semantic-color-text-body,#444);text-decoration:none;">{{ $n }}</a>
      @endforeach
    </span>
  </nav>

  {{-- hero --}}
  <section style="padding:var(--semantic-size-space-section,72px) 32px;max-width:var(--semantic-content-max-width,1100px);">
    <div style="max-width:var(--semantic-size-space-container,1280px);">
      <div style="font-family:var(--semantic-font-family-display,inherit);text-transform:uppercase;letter-spacing:0.18em;font-size:var(--semantic-font-size-sm,0.875rem);color:var(--semantic-color-brand,#3b82f6);font-weight:600;margin-bottom:1rem;">The circle · Issue 04</div>
      <h1 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-5xl,3.4rem);color:var(--semantic-color-text-heading,#111);line-height:var(--semantic-font-line-height-heading,1.05);letter-spacing:var(--semantic-font-letter-spacing-heading,-0.01em);font-weight:var(--semantic-font-weight-heading,700);margin:0 0 1.25rem;">
        A theme, drawn<br>in one breath.
      </h1>
      <div style="height:2px;width:64px;background:var(--semantic-color-text-heading,#111);margin:0 0 1.5rem;"></div>
      <p style="font-size:var(--semantic-font-size-lg,1.25rem);color:var(--semantic-color-text-body,#444);max-width:var(--semantic-content-prose-max-width,60ch);line-height:var(--semantic-font-line-height-body,1.6);margin:0 0 1.75rem;">
        This is a live preview — real type, real color, real spacing from the theme's tokens. Change a token and this page changes with it, exactly as your published site will.
      </p>
      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <a href="#" style="display:inline-block;background:var(--semantic-btn-bg,var(--semantic-color-brand,#3b82f6));color:var(--semantic-btn-color,#fff);padding:var(--semantic-btn-padding,14px 30px);font-family:var(--semantic-font-family-button,inherit);font-weight:var(--semantic-btn-font-weight,600);letter-spacing:var(--semantic-btn-tracking,0.02em);text-transform:var(--semantic-btn-transform,none);border-radius:var(--semantic-size-radius-md,0.375rem);text-decoration:none;">Read the issue</a>
        <a href="#" style="display:inline-block;background:transparent;color:var(--semantic-color-text-heading,#111);padding:var(--semantic-btn-padding,14px 30px);border:1px solid var(--semantic-color-border-strong,#999);border-radius:var(--semantic-size-radius-md,0.375rem);font-family:var(--semantic-font-family-button,inherit);font-weight:600;letter-spacing:var(--semantic-btn-tracking,0.02em);text-transform:var(--semantic-btn-transform,none);text-decoration:none;">About the studio</a>
      </div>
    </div>
  </section>

  {{-- card grid --}}
  <section style="padding:0 32px var(--semantic-size-space-section,72px);">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:var(--semantic-size-space-gap,28px);">
      @foreach([['Ваби-саби','Beauty in the incomplete','The imperfect, impermanent, unfinished — held with attention.'],['Гласове','Voices & texts','Editorial rhythm: a display face over a quiet body.'],['Движение','Motion, quietly','Cinematic layout without decoration. The grid does the work.']] as $c)
        <div style="background:var(--semantic-color-background-raised,#fff);border:1px solid var(--semantic-color-border-default,#e5e5e5);border-radius:var(--semantic-size-radius-md,0.375rem);box-shadow:var(--semantic-shadow-md,none);padding:28px;">
          <div style="font-family:var(--semantic-font-family-display,inherit);text-transform:uppercase;letter-spacing:0.14em;font-size:var(--semantic-font-size-xs,0.72rem);color:var(--semantic-color-brand,#3b82f6);font-weight:600;margin-bottom:0.5rem;">{{ $c[0] }}</div>
          <h3 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-xl,1.4rem);color:var(--semantic-color-text-heading,#111);margin:0 0 0.5rem;line-height:1.15;">{{ $c[1] }}</h3>
          <p style="color:var(--semantic-color-text-muted,#777);font-size:var(--semantic-font-size-sm,0.875rem);margin:0;line-height:1.5;">{{ $c[2] }}</p>
        </div>
      @endforeach
    </div>
  </section>

  {{-- type specimen strip --}}
  <section style="padding:var(--semantic-size-space-section,72px) 32px;background:var(--semantic-color-background-surface,#f5f5f5);border-top:1px solid var(--semantic-color-border-subtle,#eee);">
    <div style="display:flex;gap:48px;flex-wrap:wrap;align-items:baseline;">
      <div>
        <div style="font-size:var(--semantic-font-size-xs,0.72rem);text-transform:uppercase;letter-spacing:0.14em;color:var(--semantic-color-text-muted,#999);margin-bottom:8px;">Display</div>
        <div style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-4xl,2.5rem);color:var(--semantic-color-text-heading,#111);line-height:1;">Ag</div>
      </div>
      <div>
        <div style="font-size:var(--semantic-font-size-xs,0.72rem);text-transform:uppercase;letter-spacing:0.14em;color:var(--semantic-color-text-muted,#999);margin-bottom:8px;">Body</div>
        <div style="font-family:var(--semantic-font-family-body,inherit);font-size:var(--semantic-font-size-4xl,2.5rem);color:var(--semantic-color-text-heading,#111);line-height:1;">Ag</div>
      </div>
      <div style="flex:1;min-width:220px;">
        <p style="font-family:var(--semantic-font-family-body,inherit);color:var(--semantic-color-text-body,#444);margin:0;line-height:var(--semantic-font-line-height-body,1.6);">
          The quick brown fox jumps over the lazy dog. <a href="#" style="color:var(--semantic-color-text-link,#3b82f6);">A themed link</a>, sized and spaced by tokens.
        </p>
      </div>
    </div>
  </section>

  {{-- footer --}}
  <footer style="background:var(--semantic-footer-bg,#111);color:var(--semantic-footer-color,#aaa);padding:40px 32px;border-top:1px solid var(--semantic-footer-border-color,#333);font-size:var(--semantic-font-size-sm,0.875rem);">
    © Ensōdo — a live theme preview. What you see is what you publish.
  </footer>
</div>
