{{-- Cytechno — Home page (Slice 1) --}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'home'])

@section('content')
<div class="fadein">

  {{-- ═══ Hero ═══ --}}
  <section class="hero">
    <div class="ph ph-bg r219 dark" data-label="HERO BACKGROUND · TECHNICAL NETWORK IMAGE"></div>
    <div class="wrap">
      <span class="eyebrow">Cybertechnology · Est. 2004 · Sofia</span>
      <h1 class="mt-m">Engineering <span class="red">secure digital</span> infrastructure</h1>
      <p class="lead mt-m" style="max-width:52ch">
        Two decades of mission-critical web development and IT solutions — trusted by Bulgarian
        government institutions, healthcare organisations and private enterprises to deliver platforms
        that are secure, scalable and built to last.
      </p>
      <div class="cta-actions mt-l" style="justify-content:flex-start">
        <a href="/contacts" class="btn btn--primary">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--ghost">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

  {{-- ═══ About teaser ═══ --}}
  <section class="section">
    <div class="wrap grid cols-2" style="align-items:start;gap:clamp(34px,5vw,72px)">
      <div>
        <div class="section-head">
          <span class="eyebrow">Who we are</span>
          <h2 class="section-title">Two decades of<br><span class="red">technical excellence</span></h2>
        </div>
        <p class="lead">Cybertechnology is a professional web development and IT infrastructure company
          headquartered in Sofia. Since 2004 we have engineered systems that operate reliably, securely and
          at scale for clients who cannot afford downtime.</p>
        <p class="muted mt-s">We design, build and maintain enterprise platforms, secure government portals,
          healthcare information systems and scalable architectures for organisations that require long-term,
          trusted partnerships rather than one-off projects.</p>
        <div class="attrib mt-m">
          <b>Nikolay Petrov</b><span>Founder &amp; CEO</span>
        </div>
        <a href="/about" class="btn btn--ghost mt-m">More about the studio <span class="arw" aria-hidden="true">→</span></a>
      </div>
      <div class="stat-grid">
        <div class="stat"><b>20+</b><span>Years on market</span></div>
        <div class="stat"><b>150+</b><span>Projects delivered</span></div>
        <div class="stat"><b>Gov &amp; Private</b><span>Trusted sectors</span></div>
        <div class="stat"><b>Long-Term</b><span>Client partnerships</span></div>
      </div>
    </div>
  </section>

  {{-- ═══ Services overview (first 3) ═══ --}}
  <section class="section section--alt">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">What we do</span>
        <h2 class="section-title">Core capabilities</h2>
        <a href="/services" class="btn btn--ghost" style="align-self:flex-start">All services <span class="arw" aria-hidden="true">→</span></a>
      </div>
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
      </div>
    </div>
  </section>

  {{-- ═══ Selected projects (3 cards) ═══ --}}
  <section class="section">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Our work</span>
        <h2 class="section-title">Selected projects</h2>
        <a href="/portfolio" class="btn btn--ghost" style="align-self:flex-start">View all projects <span class="arw" aria-hidden="true">→</span></a>
      </div>
      <div class="grid cols-3">
        {{-- InvestBG --}}
        <a class="card" href="/portfolio/investbg">
          <div class="ph r43" data-label="PROJECT · INVESTBG · LEAD IMAGE"></div>
          <div class="body">
            <span class="cat">Government · Investment Platform</span>
            <h3>InvestBG</h3>
            <p>Official web platform of the Bulgarian Investment Agency — designed to attract foreign direct investment and connect international institutional partners.</p>
            <span class="meta txtlink">Visit project <span class="arw" aria-hidden="true">→</span></span>
          </div>
        </a>
        {{-- ArtDay --}}
        <a class="card" href="/portfolio/artday">
          <div class="ph r43" data-label="PROJECT · ARTDAY · LEAD IMAGE"></div>
          <div class="body">
            <span class="cat">Media · Culture &amp; Arts</span>
            <h3>ArtDay.bg</h3>
            <p>Bulgaria's leading cultural media platform — contemporary art, exhibitions and lifestyle features delivered to a growing national audience.</p>
            <span class="meta txtlink">Visit project <span class="arw" aria-hidden="true">→</span></span>
          </div>
        </a>
        {{-- NCTH --}}
        <a class="card" href="/portfolio/ncth">
          <div class="ph r43" data-label="PROJECT · NCTH · LEAD IMAGE"></div>
          <div class="body">
            <span class="cat">Government · Healthcare</span>
            <h3>NCTH</h3>
            <p>Website for the National Centre of Transfusion Haematology — managing blood-donation services, donor registries and transfusion-medicine information.</p>
            <span class="meta txtlink">Visit project <span class="arw" aria-hidden="true">→</span></span>
          </div>
        </a>
      </div>
    </div>
  </section>

  {{-- ═══ Ideas + Blog teaser (2 columns) ═══ --}}
  <section class="section section--alt">
    <div class="wrap grid cols-2" style="gap:clamp(34px,5vw,64px)">
      {{-- Ideas column --}}
      <div>
        <div class="section-head">
          <span class="eyebrow">Ideas</span>
          <h2 class="section-title">Visionary essays</h2>
        </div>
        <div class="artlist">
          <a class="artrow" href="/ideas/free-software-civic-infrastructure" style="grid-template-columns:1fr 40px">
            <div>
              <h3>Free Software as Civic Infrastructure</h3>
              <p>Software that runs the state should be inspectable by the public it serves. A position on why public-sector platforms belong to the public.</p>
            </div>
            <span class="arw" aria-hidden="true">→</span>
          </a>
          <a class="artrow" href="/ideas/structured-content-types" style="grid-template-columns:1fr 40px">
            <div>
              <h3>Structured Content Types Are the Future of CMS</h3>
              <p>Pages and posts were a good start. Typed fields, validated schemas and first-class relations are how the CMS grows up.</p>
            </div>
            <span class="arw" aria-hidden="true">→</span>
          </a>
        </div>
        <a href="/ideas" class="btn btn--ghost mt-m">All ideas <span class="arw" aria-hidden="true">→</span></a>
      </div>
      {{-- Blog column --}}
      <div>
        <div class="section-head">
          <span class="eyebrow">Blog</span>
          <h2 class="section-title">From the studio</h2>
        </div>
        <div class="artlist">
          <a class="artrow" href="/blog/server-rendered-html-2026" style="grid-template-columns:1fr 40px;padding:24px 0">
            <div>
              <span class="date">12 May 2026</span>
              <h3 style="font-size:1.35rem;margin:8px 0 0">Why We Still Ship Server-Rendered HTML in 2026</h3>
            </div>
            <span class="arw" aria-hidden="true">→</span>
          </a>
          <a class="artrow" href="/blog/hardening-tls-government-portals" style="grid-template-columns:1fr 40px;padding:24px 0">
            <div>
              <span class="date">28 Mar 2026</span>
              <h3 style="font-size:1.35rem;margin:8px 0 0">Hardening SSL/TLS for Government Portals</h3>
            </div>
            <span class="arw" aria-hidden="true">→</span>
          </a>
          <a class="artrow" href="/blog/content-hierarchy-cms" style="grid-template-columns:1fr 40px;padding:24px 0">
            <div>
              <span class="date">04 Feb 2026</span>
              <h3 style="font-size:1.35rem;margin:8px 0 0">Content Hierarchy Is the Architecture of a CMS</h3>
            </div>
            <span class="arw" aria-hidden="true">→</span>
          </a>
        </div>
        <a href="/blog" class="btn btn--ghost mt-m">All articles <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

  {{-- ═══ CTA band (dark) ═══ --}}
  <section class="section cta-band section--dark">
    <div class="wrap stack" style="align-items:center">
      <span class="eyebrow on-dark">Get in touch</span>
      <h2>Let's build secure digital systems together</h2>
      <div class="cta-actions">
        <a href="/contacts" class="btn btn--light">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
