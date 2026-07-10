{{-- PORTFOLIO (Atelier): image-led. A minimal bar, one huge full-bleed project
     with the title overlaid, then an asymmetric gallery grid with index nos. --}}
@php
    $g = fn($a,$b) => "linear-gradient(135deg,$a,$b)";
    $tiles = [
        ['01','Kinfolk Studio','Brand & print', $g('var(--semantic-color-text-muted,#999)','var(--semantic-color-background-inverse,#111)')],
        ['02','Aperture','Photography', $g('var(--semantic-color-brand,#e34234)','var(--semantic-color-text-heading,#111)')],
        ['03','Meridian','Art direction', $g('var(--semantic-color-background-inverse,#111)','var(--semantic-color-text-muted,#888)')],
        ['04','Salt & Stone','Packaging', $g('var(--semantic-color-text-heading,#222)','var(--semantic-color-brand,#e34234)')],
    ];
@endphp
<div style="font-family:var(--semantic-font-family-body,sans-serif);color:var(--semantic-color-text-body,#333);background:var(--semantic-color-background-canvas,#fff);">

  {{-- minimal bar --}}
  <nav style="display:flex;align-items:center;justify-content:space-between;padding:22px 36px;">
    <span style="font-family:var(--semantic-font-family-display,inherit);font-weight:700;font-size:17px;letter-spacing:0.02em;color:var(--semantic-color-text-heading,#111);">Atelier<span style="color:var(--semantic-color-brand,#e34234);">.</span></span>
    <span style="display:flex;gap:24px;font-size:13px;text-transform:uppercase;letter-spacing:0.14em;color:var(--semantic-color-text-body,#555);"><span>Work</span><span>Studio</span><span>Contact</span></span>
  </nav>

  {{-- huge full-bleed project with overlaid title --}}
  <section style="position:relative;height:460px;background:{{ $g('var(--semantic-color-background-inverse,#111)','var(--semantic-color-text-muted,#777)') }};display:flex;align-items:flex-end;padding:40px 36px;overflow:hidden;">
    <div style="position:absolute;top:20px;right:36px;font-family:var(--semantic-font-family-display,inherit);font-size:13px;letter-spacing:0.16em;text-transform:uppercase;color:rgba(255,255,255,0.7);">Featured · 2026</div>
    <div style="color:#fff;">
      <div style="font-size:13px;text-transform:uppercase;letter-spacing:0.18em;opacity:0.8;margin-bottom:12px;">Identity · Web · Print</div>
      <h1 style="font-family:var(--semantic-font-family-display,inherit);font-size:clamp(44px,7vw,88px);line-height:0.95;letter-spacing:-0.02em;margin:0;max-width:16ch;">The quiet luxury of restraint</h1>
    </div>
  </section>

  {{-- gallery grid, asymmetric --}}
  <section style="padding:var(--semantic-size-space-section,72px) 36px;">
    <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:28px;">
      <h2 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-2xl,2rem);color:var(--semantic-color-text-heading,#111);margin:0;">Selected work</h2>
      <span style="font-size:13px;text-transform:uppercase;letter-spacing:0.14em;color:var(--semantic-color-text-muted,#999);">View all →</span>
    </div>
    <div style="display:grid;grid-template-columns:1.4fr 1fr;grid-auto-rows:220px;gap:16px;">
      @foreach($tiles as $i => $t)
        <figure style="position:relative;margin:0;background:{{ $t[3] }};overflow:hidden;{{ $i===0 ? 'grid-row:span 2;' : '' }}">
          <span style="position:absolute;top:14px;left:16px;font-family:var(--semantic-font-family-display,inherit);font-size:13px;color:rgba(255,255,255,0.85);letter-spacing:0.1em;">{{ $t[0] }}</span>
          <figcaption style="position:absolute;left:0;right:0;bottom:0;padding:16px;background:linear-gradient(transparent,rgba(0,0,0,0.55));color:#fff;">
            <div style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-lg,1.3rem);line-height:1.1;">{{ $t[1] }}</div>
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.14em;opacity:0.8;margin-top:4px;">{{ $t[2] }}</div>
          </figcaption>
        </figure>
      @endforeach
    </div>
  </section>

  <footer style="padding:40px 36px;border-top:1px solid var(--semantic-color-border-default,#e5e5e5);display:flex;justify-content:space-between;align-items:center;">
    <span style="font-family:var(--semantic-font-family-display,inherit);font-size:clamp(24px,3vw,40px);color:var(--semantic-color-text-heading,#111);">Let's make something.</span>
    <a href="#" style="background:var(--semantic-btn-bg,var(--semantic-color-brand,#e34234));color:var(--semantic-btn-color,#fff);padding:14px 28px;font-weight:600;letter-spacing:var(--semantic-btn-tracking,0.02em);text-transform:var(--semantic-btn-transform,none);border-radius:var(--semantic-size-radius-md,0);text-decoration:none;">Start a project</a>
  </footer>
</div>
