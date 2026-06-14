{{-- Cytechno — About page (Slice 2) --}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'about'])

@section('content')
<div class="fadein">

  {{-- ═══ Page hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">About the studio</span>
      <h1>We build infrastructure meant to outlive the trend</h1>
      <p class="lead mt-m" style="max-width:54ch">Cybertechnology is a Sofia-based web development and IT infrastructure company. Since 2004 we have engineered platforms for organisations that cannot afford downtime — and cannot afford to be locked in.</p>
    </div>
  </section>

  {{-- ═══ Story & approach ═══ --}}
  <section class="section">
    <div class="wrap grid cols-2" style="gap:clamp(34px,5vw,72px);align-items:start">
      <div>
        <div class="section-head">
          <span class="eyebrow">Story &amp; approach</span>
          <h2 class="section-title">Deliberate, not fashionable</h2>
        </div>
        <p class="lead">Our approach is deliberate: clean code, proven technologies, no bloated frameworks —
          purpose-built digital infrastructure engineered to stand the test of time.</p>
        <p class="muted mt-s">We design, build and maintain enterprise platforms, secure government portals,
          healthcare information systems and scalable web architectures. Most of our relationships are measured
          in years, not deliverables — because the systems we build are meant to run for a decade or more.</p>
        <p class="muted mt-s">We favour long-term partnerships over one-off projects. That bias shows up in
          everything: documentation, knowledge transfer, and a roadmap our clients own outright.</p>
      </div>
      <div class="ph r43" data-label="STUDIO · TEAM / OFFICE IMAGE"></div>
    </div>
  </section>

  {{-- ═══ Values grid (4-col, red top-rule) ═══ --}}
  <section class="section section--alt">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">What we value</span>
        <h2 class="section-title">Four commitments</h2>
      </div>
      <div class="grid cols-4">
        <div class="stack gap-s" style="border-top:2px solid var(--red);padding-top:20px">
          <h3 class="cond" style="font-size:1.45rem">Security first</h3>
          <p class="muted" style="font-size:.92rem">TLS, headers and isolation hardened from day one — the boring layers that keep platforms standing.</p>
        </div>
        <div class="stack gap-s" style="border-top:2px solid var(--red);padding-top:20px">
          <h3 class="cond" style="font-size:1.45rem">Built to last</h3>
          <p class="muted" style="font-size:.92rem">Proven, dull, well-understood tools chosen for thirty-year decisions, not launch-day benchmarks.</p>
        </div>
        <div class="stack gap-s" style="border-top:2px solid var(--red);padding-top:20px">
          <h3 class="cond" style="font-size:1.45rem">Owned, not rented</h3>
          <p class="muted" style="font-size:.92rem">Clients own their infrastructure and their roadmap — never stranded inside a vendor's product.</p>
        </div>
        <div class="stack gap-s" style="border-top:2px solid var(--red);padding-top:20px">
          <h3 class="cond" style="font-size:1.45rem">Measured, not assumed</h3>
          <p class="muted" style="font-size:.92rem">PageSpeed, accessibility and audits are measured and enforced, not promised.</p>
        </div>
      </div>
    </div>
  </section>

  {{-- ═══ Free-software philosophy ═══ --}}
  <section class="section">
    <div class="wrap">
      <div class="fs-block">
        <div>
          <span class="tag-fs">Free-software philosophy</span>
          <h3>Software that runs the state should be inspectable by the public it serves</h3>
          <p>We release the tools beneath our platforms as free software and support them as such. It is the only
            honest way to build infrastructure meant to outlive any single vendor — including us. Read the argument
            in full in our Ideas essays.</p>
        </div>
        <a href="/ideas/free-software-civic-infrastructure" class="btn btn--primary">Read the essay <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

  {{-- ═══ Leadership stats ═══ --}}
  <section class="section section--alt">
    <div class="wrap grid cols-2" style="gap:clamp(34px,5vw,72px);align-items:center">
      <div class="stat-grid">
        <div class="stat"><b>20+</b><span>Years on market</span></div>
        <div class="stat"><b>150+</b><span>Projects delivered</span></div>
        <div class="stat"><b>Sofia</b><span>Headquartered in Bulgaria</span></div>
        <div class="stat"><b>2004</b><span>Founded</span></div>
      </div>
      <div>
        <div class="section-head">
          <span class="eyebrow">Leadership</span>
          <h2 class="section-title">Run by engineers</h2>
        </div>
        <p class="lead">The studio is led by founder and CEO Nikolay Petrov, who has guided Cybertechnology
          from a small Sofia practice into a trusted partner for national institutions.</p>
        <div class="attrib mt-m"><b>Nikolay Petrov</b><span>Founder &amp; CEO</span></div>
      </div>
    </div>
  </section>

  {{-- ═══ CTA band ═══ --}}
  <section class="section cta-band section--dark">
    <div class="wrap stack" style="align-items:center">
      <span class="eyebrow on-dark">Get in touch</span>
      <h2>Let's build something that lasts</h2>
      <div class="cta-actions">
        <a href="/contacts" class="btn btn--light">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
