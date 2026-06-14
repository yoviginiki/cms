{{-- Cytechno — Services landing (Slice 3) --}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'services'])

@section('content')
<div class="fadein">

  {{-- ═══ Page hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">Services</span>
      <h1>Engineering, security and the discipline to keep them green</h1>
      <p class="lead mt-m" style="max-width:54ch">From custom platforms to TLS hardening and long-term support — each engagement starts from the data model and ends in a partnership, not a deliverable.</p>
    </div>
  </section>

  {{-- ═══ Full services list ═══ --}}
  <section class="section">
    <div class="wrap">
      <div class="rowlist">
        <a class="rowitem" href="/services/custom-web-development">
          <span class="num">01</span>
          <h3>Custom Web Development</h3>
          <p>Enterprise websites, web applications and custom CMS platforms — engineered for scalability, security and long-term performance.</p>
          <span class="arw" aria-hidden="true">→</span>
        </a>
        <a class="rowitem" href="/services/cms-platform-engineering">
          <span class="num">02</span>
          <h3>CMS &amp; Platform Engineering</h3>
          <p>Editor-first content platforms with a block model, static publishing and a hierarchy that mirrors how your organisation actually works.</p>
          <span class="arw" aria-hidden="true">→</span>
        </a>
        <a class="rowitem" href="/services/infrastructure-security">
          <span class="num">03</span>
          <h3>IT Infrastructure &amp; Security</h3>
          <p>Secure hosting environments, server architecture, infrastructure modernisation and SSL/TLS hardening for demanding production workloads.</p>
          <span class="arw" aria-hidden="true">→</span>
        </a>
        <a class="rowitem" href="/services/cloud-hosting">
          <span class="num">04</span>
          <h3>Cloud &amp; Managed Hosting</h3>
          <p>Predictable, sovereign hosting for government portals and healthcare systems — sized to load, audited, and quietly maintained.</p>
          <span class="arw" aria-hidden="true">→</span>
        </a>
        <a class="rowitem" href="/services/api-integration-automation">
          <span class="num">05</span>
          <h3>API Integration &amp; Automation</h3>
          <p>Connecting registries, payment rails, identity providers and legacy systems into platforms that exchange data reliably.</p>
          <span class="arw" aria-hidden="true">→</span>
        </a>
        <a class="rowitem" href="/services/technical-consulting-support">
          <span class="num">06</span>
          <h3>Technical Consulting &amp; Support</h3>
          <p>Technology advisory, performance audits, strategic IT roadmapping and ongoing 24/7 support for mission-critical platforms.</p>
          <span class="arw" aria-hidden="true">→</span>
        </a>
      </div>
    </div>
  </section>

  {{-- ═══ How we work — 4-step process ═══ --}}
  <section class="section section--alt">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">How we work</span>
        <h2 class="section-title">A four-step partnership</h2>
      </div>
      <div class="grid cols-4">
        <div class="stack gap-s" style="border-top:2px solid var(--red);padding-top:20px">
          <span class="num cond" style="color:var(--ink-3);font-size:1.1rem">01</span>
          <h3 class="cond" style="font-size:1.45rem">Discovery &amp; Audit</h3>
          <p class="muted" style="font-size:.92rem">We map the content, the constraints and the real users before proposing anything. Government, healthcare and culture each carry different rules.</p>
        </div>
        <div class="stack gap-s" style="border-top:2px solid var(--red);padding-top:20px">
          <span class="num cond" style="color:var(--ink-3);font-size:1.1rem">02</span>
          <h3 class="cond" style="font-size:1.45rem">Architecture</h3>
          <p class="muted" style="font-size:.92rem">The data model and content hierarchy come first — types, fields, roles and the publish pipeline — drawn before any interface.</p>
        </div>
        <div class="stack gap-s" style="border-top:2px solid var(--red);padding-top:20px">
          <span class="num cond" style="color:var(--ink-3);font-size:1.1rem">03</span>
          <h3 class="cond" style="font-size:1.45rem">Build &amp; Harden</h3>
          <p class="muted" style="font-size:.92rem">Clean, server-rendered code; static publishing; TLS and headers hardened; PageSpeed measured, not assumed.</p>
        </div>
        <div class="stack gap-s" style="border-top:2px solid var(--red);padding-top:20px">
          <span class="num cond" style="color:var(--ink-3);font-size:1.1rem">04</span>
          <h3 class="cond" style="font-size:1.45rem">Handover &amp; Partnership</h3>
          <p class="muted" style="font-size:.92rem">Documentation, knowledge transfer and a maintenance partnership. The platform is yours to run, with us behind it.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- ═══ CTA ═══ --}}
  <section class="section cta-band section--dark">
    <div class="wrap stack" style="align-items:center">
      <span class="eyebrow on-dark">Get in touch</span>
      <h2>Tell us what needs to run for a decade</h2>
      <div class="cta-actions">
        <a href="/contacts" class="btn btn--light">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
