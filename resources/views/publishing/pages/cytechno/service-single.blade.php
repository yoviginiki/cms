{{-- Cytechno — Single Service page (Slice 3)
     Variables: $service = { slug, n, title, summary, included[], approach }
                $relatedProjects = [ { slug, name, cat, excerpt } ... ]
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'services'])

@section('content')
<div class="fadein">

  {{-- ═══ Service hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <a href="/services" class="txtlink" style="margin-bottom:18px"><span class="arw">←</span> All services</a>
      <span class="eyebrow">Service · {{ $service['n'] ?? '01' }}</span>
      <h1>{{ $service['title'] ?? 'Service' }}</h1>
      <p class="lead mt-m" style="max-width:56ch">{{ $service['summary'] ?? '' }}</p>
    </div>
  </section>

  {{-- ═══ What's included + Approach ═══ --}}
  <section class="section">
    <div class="wrap grid cols-2" style="gap:clamp(34px,5vw,72px);align-items:start">
      <div>
        <div class="section-head">
          <span class="eyebrow">What's included</span>
          <h2 class="section-title">Scope</h2>
        </div>
        <ul class="feature-list">
          @foreach(($service['included'] ?? []) as $i => $feature)
          <li><b>{{ $feature }}</b><span>{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span></li>
          @endforeach
        </ul>
      </div>
      <div>
        <div class="section-head">
          <span class="eyebrow">Our approach</span>
          <h2 class="section-title">How we deliver it</h2>
        </div>
        <p class="lead">{{ $service['approach'] ?? '' }}</p>
        <div class="ph r43 mt-m" data-label="SERVICE · SUPPORTING DIAGRAM"></div>
      </div>
    </div>
  </section>

  {{-- ═══ Related work ═══ --}}
  <section class="section section--alt">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Related work</span>
        <h2 class="section-title">In production</h2>
        <a href="/portfolio" class="btn btn--ghost" style="align-self:flex-start">All projects <span class="arw" aria-hidden="true">→</span></a>
      </div>
      <div class="grid cols-3">
        @foreach(($relatedProjects ?? []) as $p)
        <a class="card" href="/portfolio/{{ $p['slug'] }}">
          <div class="ph r43" data-label="PROJECT · {{ strtoupper($p['name'] ?? '') }} · LEAD IMAGE"></div>
          <div class="body">
            <span class="cat">{{ $p['cat'] ?? '' }}</span>
            <h3>{{ $p['name'] ?? '' }}</h3>
            <p>{{ $p['excerpt'] ?? '' }}</p>
            <span class="meta txtlink">Visit project <span class="arw" aria-hidden="true">→</span></span>
          </div>
        </a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ═══ CTA ═══ --}}
  <section class="section cta-band section--dark">
    <div class="wrap stack" style="align-items:center">
      <span class="eyebrow on-dark">Get in touch</span>
      <h2>Need {{ strtolower($service['title'] ?? 'our help') }}?</h2>
      <div class="cta-actions">
        <a href="/contacts" class="btn btn--light">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
