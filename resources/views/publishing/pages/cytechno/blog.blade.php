{{-- Cytechno — Blog listing (Slice 5)
     Variables: $posts = [ { slug, date, read, title, excerpt } ... ]
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'blog'])

@section('content')
<div class="fadein">

  {{-- ═══ Page hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">Blog</span>
      <h1>Field notes on building durable platforms</h1>
      <p class="lead mt-m" style="max-width:54ch">Everyday writing from the studio on web performance, security and the discipline of keeping mission-critical systems green.</p>
    </div>
  </section>

  {{-- ═══ Article rows ═══ --}}
  <section class="section">
    <div class="wrap">
      <div class="artlist">
        @foreach(($posts ?? []) as $post)
        <a class="artrow" href="/blog/{{ $post['slug'] }}">
          <span class="date">{{ $post['date'] }}<br>{{ $post['read'] ?? '' }}</span>
          <div>
            <h3>{{ $post['title'] }}</h3>
            <p>{{ $post['excerpt'] }}</p>
          </div>
          <span class="arw" aria-hidden="true">→</span>
        </a>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ═══ CTA ═══ --}}
  <section class="section cta-band section--dark">
    <div class="wrap stack" style="align-items:center">
      <span class="eyebrow on-dark">Work with us</span>
      <h2>Prefer to talk it through?</h2>
      <div class="cta-actions">
        <a href="/contacts" class="btn btn--light">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
