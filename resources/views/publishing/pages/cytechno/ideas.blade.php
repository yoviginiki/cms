{{-- Cytechno — Ideas listing (Slice 6)
     Variables: $ideas = [ { slug, date, read, title, excerpt } ... ]
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'ideas'])

@section('content')
<div class="fadein">

  {{-- ═══ Page hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">Ideas</span>
      <h1>A position on how software should serve the public</h1>
      <p class="lead mt-m" style="max-width:54ch">Longer-form essays on free software, boring technology and infrastructure built to be owned in common — the thinking beneath everything we build.</p>
    </div>
  </section>

  {{-- ═══ Essay rows ═══ --}}
  <section class="section">
    <div class="wrap">
      <div class="artlist">
        @foreach(($ideas ?? []) as $idea)
        <a class="artrow" href="/ideas/{{ $idea['slug'] }}">
          <span class="date">Essay · {{ $idea['date'] }}<br>{{ $idea['read'] ?? '' }}</span>
          <div>
            <h3>{{ $idea['title'] }}</h3>
            <p>{{ $idea['excerpt'] }}</p>
          </div>
          <span class="arw" aria-hidden="true">→</span>
        </a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ═══ CTA (links to Products) ═══ --}}
  <section class="section cta-band section--dark">
    <div class="wrap stack" style="align-items:center">
      <span class="eyebrow on-dark">Free software</span>
      <h2>We back our ideas with code</h2>
      <div class="cta-actions">
        <a href="/products" class="btn btn--light">Explore products <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
