{{-- BUSINESS (Ledger): utility nav + CTA, split hero with a stat panel, a
     4-metric band, a 3-up feature grid with icon tiles. Tight, structured. --}}
<div style="font-family:var(--semantic-font-family-body,system-ui,sans-serif);color:var(--semantic-color-text-body,#334);background:var(--semantic-color-background-canvas,#fff);">

  {{-- nav with CTA --}}
  <nav style="display:flex;align-items:center;justify-content:space-between;padding:16px 40px;border-bottom:1px solid var(--semantic-color-border-default,#e5e7eb);">
    <span style="font-family:var(--semantic-font-family-display,inherit);font-weight:700;font-size:18px;color:var(--semantic-color-text-heading,#111);letter-spacing:-0.01em;display:flex;align-items:center;gap:8px;"><span style="width:18px;height:18px;background:var(--semantic-color-brand,#2563eb);display:inline-block;border-radius:var(--semantic-size-radius-sm,4px);"></span>Ledger</span>
    <span style="display:flex;gap:26px;align-items:center;font-size:14px;color:var(--semantic-color-text-body,#475);">
      <span>Product</span><span>Pricing</span><span>Customers</span><span>Docs</span>
      <a href="#" style="background:var(--semantic-btn-bg,var(--semantic-color-brand,#2563eb));color:var(--semantic-btn-color,#fff);padding:9px 18px;border-radius:var(--semantic-size-radius-md,6px);font-weight:600;font-size:13px;text-decoration:none;">Start free</a>
    </span>
  </nav>

  {{-- split hero --}}
  <section style="padding:64px 40px;display:grid;grid-template-columns:1.1fr 0.9fr;gap:48px;align-items:center;">
    <div>
      <div style="display:inline-block;font-size:12px;font-weight:600;color:var(--semantic-color-brand,#2563eb);background:var(--semantic-color-background-surface,#eff4ff);padding:5px 12px;border-radius:var(--semantic-size-radius-full,999px);margin-bottom:18px;">New · Automated reconciliation</div>
      <h1 style="font-family:var(--semantic-font-family-display,inherit);font-size:clamp(34px,4.5vw,52px);line-height:1.08;letter-spacing:-0.02em;color:var(--semantic-color-text-heading,#0f172a);margin:0 0 16px;">Numbers that reconcile themselves.</h1>
      <p style="font-size:var(--semantic-font-size-lg,1.15rem);line-height:1.6;color:var(--semantic-color-text-muted,#64748b);max-width:46ch;margin:0 0 24px;">A restrained, fast-scanning business layout — clear hierarchy, generous whitespace, and stats front and center. This is a live token-driven preview.</p>
      <div style="display:flex;gap:12px;">
        <a href="#" style="background:var(--semantic-btn-bg,var(--semantic-color-brand,#2563eb));color:var(--semantic-btn-color,#fff);padding:12px 24px;border-radius:var(--semantic-size-radius-md,6px);font-weight:600;text-decoration:none;">Get started</a>
        <a href="#" style="border:1px solid var(--semantic-color-border-strong,#cbd5e1);color:var(--semantic-color-text-heading,#334);padding:12px 24px;border-radius:var(--semantic-size-radius-md,6px);font-weight:600;text-decoration:none;">Book a demo</a>
      </div>
    </div>
    {{-- product/stat panel --}}
    <div style="background:var(--semantic-color-background-surface,#f8fafc);border:1px solid var(--semantic-color-border-default,#e5e7eb);border-radius:var(--semantic-size-radius-lg,12px);box-shadow:var(--semantic-shadow-lg,0 10px 30px rgba(0,0,0,0.06));padding:24px;">
      <div style="font-size:12px;color:var(--semantic-color-text-muted,#94a3b8);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:14px;">This quarter</div>
      @foreach([['Revenue','$248,300','+12.4%'],['Open invoices','18','−3'],['Reconciled','99.2%','+0.6%']] as $r)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--semantic-color-border-subtle,#eef2f6);">
          <span style="color:var(--semantic-color-text-muted,#64748b);font-size:14px;">{{ $r[0] }}</span>
          <span style="display:flex;gap:10px;align-items:baseline;"><b style="color:var(--semantic-color-text-heading,#0f172a);font-size:17px;">{{ $r[1] }}</b><span style="color:var(--semantic-color-success,#16a34a);font-size:12px;font-weight:600;">{{ $r[2] }}</span></span>
        </div>
      @endforeach
    </div>
  </section>

  {{-- metric band --}}
  <section style="background:var(--semantic-color-background-inverse,#0f172a);color:var(--semantic-color-text-inverse,#fff);padding:36px 40px;display:grid;grid-template-columns:repeat(4,1fr);gap:24px;text-align:center;">
    @foreach([['12k+','teams'],['$4.2B','reconciled'],['99.99%','uptime'],['4.9/5','rating']] as $m)
      <div><div style="font-family:var(--semantic-font-family-display,inherit);font-size:32px;font-weight:700;">{{ $m[0] }}</div><div style="opacity:0.7;font-size:13px;text-transform:uppercase;letter-spacing:0.08em;">{{ $m[1] }}</div></div>
    @endforeach
  </section>

  {{-- feature grid --}}
  <section style="padding:56px 40px;display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">
    @foreach([['Ledgers','Real-time double-entry that never drifts.'],['Rules','Automations that match and reconcile.'],['Reports','Board-ready exports in one click.']] as $i => $f)
      <div style="border:1px solid var(--semantic-color-border-default,#e5e7eb);border-radius:var(--semantic-size-radius-md,8px);padding:24px;background:var(--semantic-color-background-raised,#fff);box-shadow:var(--semantic-shadow-sm,none);">
        <div style="width:40px;height:40px;border-radius:var(--semantic-size-radius-md,8px);background:var(--semantic-color-background-surface,#eff4ff);display:flex;align-items:center;justify-content:center;margin-bottom:14px;"><span style="width:16px;height:16px;background:var(--semantic-color-brand,#2563eb);display:inline-block;border-radius:3px;"></span></div>
        <h3 style="font-family:var(--semantic-font-family-display,inherit);font-size:var(--semantic-font-size-lg,1.2rem);color:var(--semantic-color-text-heading,#0f172a);margin:0 0 8px;">{{ $f[0] }}</h3>
        <p style="color:var(--semantic-color-text-muted,#64748b);font-size:14px;line-height:1.55;margin:0;">{{ $f[1] }}</p>
      </div>
    @endforeach
  </section>

  <footer style="background:var(--semantic-footer-bg,#f8fafc);color:var(--semantic-footer-color,#64748b);padding:28px 40px;border-top:1px solid var(--semantic-color-border-default,#e5e7eb);font-size:13px;display:flex;justify-content:space-between;">
    <span>© Ledger, Inc.</span><span>A minimal business theme</span>
  </footer>
</div>
