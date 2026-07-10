{{-- CINEMATIC (Ensō): full-bleed inverse hero, huge asymmetric condensed type,
     thin hairline rules, an editorial index list. No cards, no chrome. --}}
<div style="font-family:var(--semantic-font-family-body,sans-serif);color:var(--semantic-color-text-body,#444);background:var(--semantic-color-background-canvas,#fff);">

  {{-- full-bleed dark hero --}}
  <section style="background:var(--semantic-color-background-inverse,#1a1817);color:var(--semantic-color-text-inverse,#fbfaf7);min-height:560px;padding:40px 48px;display:flex;flex-direction:column;justify-content:space-between;position:relative;">
    <div style="display:flex;justify-content:space-between;align-items:center;font-family:var(--semantic-font-family-display,inherit);text-transform:uppercase;letter-spacing:0.22em;font-size:12px;">
      <span style="font-weight:700;">Ensōdo</span>
      <span style="display:flex;gap:28px;opacity:0.8;"><span>Work</span><span>Journal</span><span>Studio</span><span>Contact</span></span>
    </div>
    <div>
      <div style="width:56px;height:2px;background:var(--semantic-color-brand,#e34234);margin-bottom:28px;"></div>
      <div style="font-family:var(--semantic-font-family-display,inherit);text-transform:uppercase;letter-spacing:0.2em;font-size:13px;color:var(--semantic-color-brand,#e34234);margin-bottom:18px;">Тишина в движение · Issue 04</div>
      <h1 style="font-family:var(--semantic-font-family-display,inherit);font-size:clamp(56px,8vw,112px);line-height:0.94;letter-spacing:-0.01em;font-weight:var(--semantic-font-weight-heading,600);margin:0;max-width:14ch;">The circle drawn in one breath.</h1>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:flex-end;font-size:14px;opacity:0.75;">
      <span style="max-width:44ch;line-height:1.6;">A cinematic, near-silent layout. The image fills the frame; the type does the rest. Scroll is the only interface.</span>
      <span style="font-family:var(--semantic-font-family-display,inherit);letter-spacing:0.1em;">01 / 04</span>
    </div>
  </section>

  {{-- editorial index — thin rules, no cards --}}
  <section style="padding:var(--semantic-size-space-section,96px) 48px;">
    <div style="display:flex;justify-content:space-between;align-items:baseline;border-bottom:2px solid var(--semantic-color-text-heading,#1a1817);padding-bottom:14px;margin-bottom:8px;">
      <h2 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-2xl,2rem);color:var(--semantic-color-text-heading,#1a1817);margin:0;text-transform:uppercase;letter-spacing:0.02em;">In this issue</h2>
      <span style="font-size:12px;text-transform:uppercase;letter-spacing:0.18em;color:var(--semantic-color-text-muted,#9a9384);">Selected works</span>
    </div>
    @foreach([['01','Ваби-саби','Beauty in the incomplete','Essay'],['02','Гласове и текстове','Voices &amp; texts','Interview'],['03','Движение','Motion, quietly','Field notes']] as $row)
      <div style="display:grid;grid-template-columns:64px 1fr 200px 120px;gap:24px;align-items:baseline;padding:26px 0;border-bottom:1px solid var(--semantic-color-border-default,#e7e2d7);">
        <span style="font-family:var(--semantic-font-family-display,inherit);font-size:22px;color:var(--semantic-color-brand,#e34234);">{{ $row[0] }}</span>
        <h3 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-2xl,1.9rem);color:var(--semantic-color-text-heading,#1a1817);margin:0;line-height:1.05;">{{ $row[1] }}</h3>
        <span style="color:var(--semantic-color-text-muted,#9a9384);font-size:15px;">{{ $row[2] }}</span>
        <span style="text-transform:uppercase;letter-spacing:0.14em;font-size:11px;color:var(--semantic-color-text-muted,#9a9384);text-align:right;">{{ $row[3] }}</span>
      </div>
    @endforeach
    <div style="margin-top:44px;">
      <a href="#" style="display:inline-block;background:var(--semantic-btn-bg,var(--semantic-color-brand,#e34234));color:var(--semantic-btn-color,#fff);padding:var(--semantic-btn-padding,14px 30px);font-family:var(--semantic-font-family-button,inherit);font-weight:600;letter-spacing:var(--semantic-btn-tracking,0.14em);text-transform:var(--semantic-btn-transform,uppercase);text-decoration:none;">Open the issue</a>
    </div>
  </section>

  <footer style="background:var(--semantic-footer-bg,#1a1817);color:var(--semantic-footer-color,#d8d2c4);padding:48px;font-family:var(--semantic-font-family-display,inherit);text-transform:uppercase;letter-spacing:0.14em;font-size:12px;display:flex;justify-content:space-between;">
    <span>Ensōdo</span><span>Washi &amp; vermilion — a cinematic theme</span>
  </footer>
</div>
