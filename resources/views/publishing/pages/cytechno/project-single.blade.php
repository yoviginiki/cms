{{-- Cytechno — Single Project page (Slice 4)
     Variables: $project = { slug, name, cat, year, client, tags[], overview, challenge, approach, outcome }
                $nextProject = { slug, name, cat }
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'portfolio'])

@section('content')
<div class="fadein">

  {{-- ═══ Project hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <a href="/portfolio" class="txtlink" style="margin-bottom:18px"><span class="arw">←</span> All projects</a>
      <span class="cat">{{ $project['cat'] ?? '' }}</span>
      <h1 style="margin-top:12px">{{ $project['name'] ?? 'Project' }}</h1>
      <div class="wrap-flex gap-l mt-m" style="gap:40px">
        <div class="cdetail" style="border-top:0;padding:0"><span>Client</span><b>{{ $project['client'] ?? '' }}</b></div>
        <div class="cdetail" style="border-top:0;padding:0"><span>Year</span><b>{{ $project['year'] ?? '' }}</b></div>
        <div class="cdetail" style="border-top:0;padding:0"><span>Sector</span><b>{{ explode(' · ', $project['cat'] ?? '')[0] }}</b></div>
      </div>
    </div>
  </section>

  {{-- ═══ Full-width lead image ═══ --}}
  <div class="ph r219" data-label="PROJECT · {{ strtoupper($project['name'] ?? '') }} · LEAD IMAGE"></div>

  {{-- ═══ Overview + Challenge/Approach/Outcome ═══ --}}
  <section class="section">
    <div class="wrap grid cols-2" style="gap:clamp(34px,5vw,72px);align-items:start">
      <div>
        <div class="section-head">
          <span class="eyebrow">Overview</span>
          <h2 class="section-title">The brief</h2>
        </div>
        <p class="lead">{{ $project['overview'] ?? '' }}</p>
        @if(!empty($project['tags']))
        <div class="tags mt-m">
          @foreach($project['tags'] as $tag)
          <span class="tag">{{ $tag }}</span>
          @endforeach
        </div>
        @endif
      </div>
      <div class="stack gap-l">
        <div>
          <h3 class="cond" style="font-size:1.4rem;margin-bottom:10px">Challenge</h3>
          <p class="muted">{{ $project['challenge'] ?? '' }}</p>
        </div>
        <div>
          <h3 class="cond" style="font-size:1.4rem;margin-bottom:10px">Approach</h3>
          <p class="muted">{{ $project['approach'] ?? '' }}</p>
        </div>
        <div>
          <h3 class="cond" style="font-size:1.4rem;margin-bottom:10px">Outcome</h3>
          <p class="muted">{{ $project['outcome'] ?? '' }}</p>
        </div>
      </div>
    </div>
  </section>

  {{-- ═══ Gallery ═══ --}}
  <section class="section section--alt">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Gallery</span>
        <h2 class="section-title">Selected screens</h2>
      </div>
      <div class="grid cols-3">
        <div class="ph r32" data-label="SCREEN · HOME"></div>
        <div class="ph r32" data-label="SCREEN · LISTING"></div>
        <div class="ph r32" data-label="SCREEN · DETAIL"></div>
      </div>
      <div class="ph r219 mt-m" data-label="SCREEN · FULL-WIDTH VIEW"></div>
    </div>
  </section>

  {{-- ═══ Next project ═══ --}}
  @if(!empty($nextProject))
  <section class="section">
    <div class="wrap">
      <a href="/portfolio/{{ $nextProject['slug'] }}" class="rowitem" style="border-top:1px solid var(--line);border-bottom:1px solid var(--line);grid-template-columns:auto 1fr 40px;align-items:center">
        <span class="num">Next</span>
        <div>
          <span class="cat">{{ $nextProject['cat'] ?? '' }}</span>
          <h3 style="margin-top:6px">{{ $nextProject['name'] ?? '' }}</h3>
        </div>
        <span class="arw" aria-hidden="true">→</span>
      </a>
    </div>
  </section>
  @endif

  {{-- ═══ CTA ═══ --}}
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
