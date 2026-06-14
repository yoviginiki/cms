{{-- Cytechno — Portfolio listing (Slice 4)
     Variables: $projects = [ { slug, name, cat, year, excerpt, client, tags[] } ... ]
                $sectors = [ 'All', 'Government', 'Media', ... ]
                $activeSector = 'All'
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'portfolio'])

@section('content')
<div class="fadein">

  {{-- ═══ Page hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">Portfolio</span>
      <h1>Platforms in production across government, healthcare and culture</h1>
      <p class="lead mt-m" style="max-width:54ch">A selection of the systems we have designed, built and still maintain. Each was engineered to run reliably for years — many still do.</p>
    </div>
  </section>

  {{-- ═══ Filter + grid ═══ --}}
  <section class="section">
    <div class="wrap">
      {{-- Sector filter — static links to pre-filtered pages (not client JS) --}}
      <div class="toolbar">
        <div class="group">
          <label>Filter</label>
          <div class="seg">
            @foreach(($sectors ?? ['All']) as $sector)
            <a href="{{ $sector === 'All' ? '/portfolio' : '/portfolio?sector=' . urlencode($sector) }}"
               class="{{ ($activeSector ?? 'All') === $sector ? 'on' : '' }}"
               style="text-decoration:none;display:inline-block">{{ $sector }}</a>
            @endforeach
          </div>
        </div>
        <span class="cat" style="color:var(--ink-3)">{{ count($projects ?? []) }} projects</span>
      </div>

      <div class="grid cols-3 mt-m">
        @foreach(($projects ?? []) as $p)
        <a class="card" href="/portfolio/{{ $p['slug'] }}">
          <div class="ph r43" data-label="PROJECT · {{ strtoupper($p['name']) }} · LEAD IMAGE"></div>
          <div class="body">
            <span class="cat">{{ $p['cat'] }}</span>
            <h3>{{ $p['name'] }}</h3>
            <p>{{ $p['excerpt'] }}</p>
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
      <h2>Have a platform that needs to last?</h2>
      <div class="cta-actions">
        <a href="/contacts" class="btn btn--light">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
