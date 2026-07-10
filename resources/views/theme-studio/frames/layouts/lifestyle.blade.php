{{-- LIFESTYLE (Hearth): warm & soft. Rounded hero with intro, a rounded card
     grid of stories/recipes with tag pills, a newsletter strip. Uses the
     theme's radius/shadow tokens (Hearth ships non-zero rounding). --}}
@php
    $g = fn($a,$b) => "linear-gradient(135deg,$a,$b)";
    $cards = [
        ['Slow mornings','A 10-minute ritual','Wellness', $g('var(--semantic-color-brand,#c96f4c)','var(--semantic-color-background-surface,#f3ece2)')],
        ['One-pot suppers','Warm, quick, kind','Recipes', $g('var(--semantic-color-success,#7c8b5a)','var(--semantic-color-background-surface,#f3ece2)')],
        ['The reading nook','Books for autumn','Living', $g('var(--semantic-color-warning,#d8a24a)','var(--semantic-color-background-surface,#f3ece2)')],
    ];
    $r = 'var(--semantic-size-radius-lg,18px)';
@endphp
<div style="font-family:var(--semantic-font-family-body,sans-serif);color:var(--semantic-color-text-body,#5b5045);background:var(--semantic-color-background-canvas,#faf6f0);">

  {{-- nav --}}
  <nav style="display:flex;align-items:center;justify-content:space-between;padding:20px 36px;">
    <span style="font-family:var(--semantic-font-family-display,inherit);font-weight:700;font-size:20px;color:var(--semantic-color-text-heading,#3a2f28);">Hearth</span>
    <span style="display:flex;gap:22px;align-items:center;font-size:14px;color:var(--semantic-color-text-body,#6b5d50);">
      <span>Recipes</span><span>Home</span><span>Wellness</span>
      <a href="#" style="background:var(--semantic-btn-bg,var(--semantic-color-brand,#c96f4c));color:var(--semantic-btn-color,#fff);padding:9px 18px;border-radius:var(--semantic-size-radius-full,999px);font-weight:600;font-size:13px;text-decoration:none;">Subscribe</a>
    </span>
  </nav>

  {{-- soft rounded hero --}}
  <section style="padding:32px 36px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center;background:var(--semantic-color-background-surface,#f3ece2);border-radius:{{ $r }};overflow:hidden;box-shadow:var(--semantic-shadow-md,0 8px 30px rgba(120,90,60,0.10));">
      <div style="padding:48px;">
        <div style="display:inline-block;background:var(--semantic-color-background-canvas,#fff);color:var(--semantic-color-brand,#c96f4c);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;padding:6px 14px;border-radius:var(--semantic-size-radius-full,999px);margin-bottom:16px;">This week</div>
        <h1 style="font-family:var(--semantic-font-family-display,inherit);font-size:clamp(32px,4vw,48px);line-height:1.1;color:var(--semantic-color-text-heading,#3a2f28);margin:0 0 14px;">Make your home a soft place to land.</h1>
        <p style="font-size:var(--semantic-font-size-lg,1.15rem);line-height:1.6;color:var(--semantic-color-text-body,#6b5d50);margin:0 0 20px;">Warm palettes, rounded corners, gentle shadows — a lifestyle layout with short, scannable items. A real token-driven preview.</p>
        <a href="#" style="background:var(--semantic-btn-bg,var(--semantic-color-brand,#c96f4c));color:var(--semantic-btn-color,#fff);padding:13px 26px;border-radius:var(--semantic-size-radius-full,999px);font-weight:600;text-decoration:none;display:inline-block;">Explore stories</a>
      </div>
      <div style="height:320px;background:{{ $g('var(--semantic-color-brand,#c96f4c)','var(--semantic-color-warning,#d8a24a)') }};"></div>
    </div>
  </section>

  {{-- rounded story cards with tag pills --}}
  <section style="padding:24px 36px var(--semantic-size-space-section,64px);">
    <h2 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-2xl,1.9rem);color:var(--semantic-color-text-heading,#3a2f28);margin:0 0 20px;">Fresh from the hearth</h2>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">
      @foreach($cards as $c)
        <article style="background:var(--semantic-color-background-raised,#fff);border-radius:{{ $r }};overflow:hidden;box-shadow:var(--semantic-shadow-sm,0 4px 16px rgba(120,90,60,0.08));">
          <div style="height:150px;background:{{ $c[3] }};"></div>
          <div style="padding:20px;">
            <span style="display:inline-block;background:var(--semantic-color-background-surface,#f3ece2);color:var(--semantic-color-brand,#c96f4c);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;padding:4px 10px;border-radius:var(--semantic-size-radius-full,999px);margin-bottom:10px;">{{ $c[2] }}</span>
            <h3 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-lg,1.25rem);color:var(--semantic-color-text-heading,#3a2f28);margin:0 0 6px;">{{ $c[0] }}</h3>
            <p style="color:var(--semantic-color-text-muted,#9a8b7c);font-size:14px;margin:0;line-height:1.5;">{{ $c[1] }}</p>
          </div>
        </article>
      @endforeach
    </div>
  </section>

  {{-- newsletter strip --}}
  <section style="margin:0 36px var(--semantic-size-space-section,64px);background:var(--semantic-color-background-inverse,#3a2f28);color:var(--semantic-color-text-inverse,#faf6f0);border-radius:{{ $r }};padding:40px;text-align:center;">
    <h3 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-2xl,1.8rem);margin:0 0 8px;">A little warmth, every Sunday</h3>
    <p style="opacity:0.8;margin:0 0 20px;">One short letter — a recipe, a ritual, a good book.</p>
    <span style="display:inline-flex;gap:8px;">
      <span style="background:var(--semantic-color-background-canvas,#fff);color:var(--semantic-color-text-body,#5b5045);padding:12px 18px;border-radius:var(--semantic-size-radius-full,999px);font-size:14px;">you@example.com</span>
      <a href="#" style="background:var(--semantic-btn-bg,var(--semantic-color-brand,#c96f4c));color:var(--semantic-btn-color,#fff);padding:12px 24px;border-radius:var(--semantic-size-radius-full,999px);font-weight:600;text-decoration:none;">Join</a>
    </span>
  </section>
</div>
