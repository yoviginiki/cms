{{-- MAGAZINE (Journal): centered masthead + rules, a two-column feature well
     with drop cap, a three-column story grid. Editorial, serif, print-like. --}}
<div style="font-family:var(--semantic-font-family-body,Georgia,serif);color:var(--semantic-color-text-body,#333);background:var(--semantic-color-background-canvas,#fff);">

  {{-- masthead --}}
  <header style="text-align:center;padding:26px 32px 0;">
    <div style="display:flex;justify-content:space-between;font-size:11px;text-transform:uppercase;letter-spacing:0.14em;color:var(--semantic-color-text-muted,#888);border-bottom:1px solid var(--semantic-color-border-default,#e5e5e5);padding-bottom:10px;">
      <span>Saturday Edition</span><span>Est. 1904</span><span>№ 128</span>
    </div>
    <h1 style="font-family:var(--semantic-font-family-display,Georgia,serif);font-size:clamp(40px,6vw,68px);color:var(--semantic-color-text-heading,#111);margin:18px 0 10px;letter-spacing:-0.01em;">The Journal</h1>
    <div style="display:flex;justify-content:center;gap:26px;font-size:12px;text-transform:uppercase;letter-spacing:0.16em;color:var(--semantic-color-text-body,#444);border-top:3px double var(--semantic-color-text-heading,#111);border-bottom:1px solid var(--semantic-color-border-default,#e5e5e5);padding:10px 0;">
      <span>Culture</span><span>Essays</span><span>Interviews</span><span>Review</span><span>Fiction</span>
    </div>
  </header>

  {{-- feature well: 2 columns --}}
  <section style="padding:44px 32px;display:grid;grid-template-columns:1.15fr 1fr;gap:48px;align-items:start;border-bottom:1px solid var(--semantic-color-border-default,#e5e5e5);">
    <div>
      <div style="text-transform:uppercase;letter-spacing:0.16em;font-size:12px;color:var(--semantic-color-brand,#8b1e2d);font-family:var(--semantic-font-family-body,sans-serif);margin-bottom:12px;">The Feature</div>
      <h2 style="font-family:var(--semantic-font-family-display,Georgia,serif);font-size:clamp(30px,4vw,52px);line-height:1.04;color:var(--semantic-color-text-heading,#111);margin:0 0 16px;">On the quiet persistence of the printed page</h2>
      <div style="font-size:13px;color:var(--semantic-color-text-muted,#888);font-style:italic;margin-bottom:20px;">By A. Marlowe · Photographs by the studio</div>
      <div style="height:220px;background:linear-gradient(135deg,var(--semantic-color-background-inverse,#222),var(--semantic-color-text-muted,#999));"></div>
    </div>
    <div style="column-count:1;">
      <p style="font-size:var(--semantic-font-size-lg,1.15rem);line-height:1.7;color:var(--semantic-color-text-body,#333);margin:0 0 14px;">
        <span style="float:left;font-family:var(--semantic-font-family-display,Georgia,serif);font-size:64px;line-height:0.8;padding:6px 10px 0 0;color:var(--semantic-color-brand,#8b1e2d);">T</span>
        here is a stubbornness to ink on paper that the screen has never managed to dislodge. This is a live preview — the type, the rules, the measure, all drawn from the theme's tokens, arranged as a magazine would.
      </p>
      <p style="line-height:1.7;color:var(--semantic-color-text-body,#444);margin:0 0 14px;">Columns, a considered measure, drop capitals, and hairline rules between stories. Change the serif and the whole page shifts character.</p>
      <a href="#" style="font-family:var(--semantic-font-family-body,sans-serif);text-transform:uppercase;letter-spacing:0.14em;font-size:12px;color:var(--semantic-color-text-link,#8b1e2d);text-decoration:none;border-bottom:2px solid var(--semantic-color-brand,#8b1e2d);padding-bottom:2px;">Continue reading →</a>
    </div>
  </section>

  {{-- story grid: 3 columns with vertical rules --}}
  <section style="padding:36px 32px var(--semantic-size-space-section,64px);display:grid;grid-template-columns:repeat(3,1fr);gap:0;">
    @foreach([['Essays','The long afternoon of the novel','R. Okafor'],['Interviews','A conversation with the binder','S. Devi'],['Review','Three exhibitions worth the train','M. Holt']] as $i => $s)
      <article style="padding:0 28px;{{ $i > 0 ? 'border-left:1px solid var(--semantic-color-border-default,#e5e5e5);' : '' }}">
        <div style="text-transform:uppercase;letter-spacing:0.16em;font-size:11px;color:var(--semantic-color-brand,#8b1e2d);font-family:var(--semantic-font-family-body,sans-serif);margin-bottom:10px;">{{ $s[0] }}</div>
        <h3 style="font-family:var(--semantic-font-family-display,Georgia,serif);font-size:var(--semantic-font-size-xl,1.5rem);line-height:1.15;color:var(--semantic-color-text-heading,#111);margin:0 0 10px;">{{ $s[1] }}</h3>
        <p style="font-size:14px;line-height:1.6;color:var(--semantic-color-text-muted,#777);margin:0 0 10px;">A brief standfirst sets the scene, measured and unhurried, in the theme's body face.</p>
        <div style="font-size:12px;font-style:italic;color:var(--semantic-color-text-muted,#999);">By {{ $s[2] }}</div>
      </article>
    @endforeach
  </section>

  <footer style="background:var(--semantic-footer-bg,#f5f2ec);color:var(--semantic-footer-color,#777);padding:32px;text-align:center;font-size:13px;border-top:3px double var(--semantic-color-text-heading,#111);">
    The Journal — a magazine theme. Set in the theme's serif.
  </footer>
</div>
