{{-- Cytechno — Single Idea / Essay (Slice 6)
     Variables: $idea = { slug, title, date, read, body[] }
                $linkedProduct = { slug, name, short } | null
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'ideas'])

@section('content')
<div class="fadein">

  {{-- ═══ Centered hero ═══ --}}
  <section class="page-hero">
    <div class="wrap" style="max-width:860px;margin-inline:auto">
      <a href="/ideas" class="txtlink" style="margin-bottom:18px"><span class="arw">←</span> All ideas</a>
      <span class="eyebrow">Essay · {{ $idea['date'] ?? '' }}</span>
      <h1 style="font-size:clamp(2.1rem,5.4vw,4.4rem);margin-top:14px">{{ $idea['title'] ?? 'Essay' }}</h1>
    </div>
  </section>

  {{-- ═══ Prose body + free-software callout + attribution ═══ --}}
  <section class="section">
    <div class="wrap" style="max-width:860px;margin-inline:auto">

      {{-- Prose --}}
      <div class="prose">
        @foreach(($idea['body'] ?? []) as $block)
          @if(!empty($block['h']))
            <h2>{{ $block['h'] }}</h2>
          @elseif(!empty($block['q']))
            <blockquote>{{ $block['q'] }}</blockquote>
          @elseif(!empty($block['ul']))
            <ul>
              @foreach($block['ul'] as $li)
                <li>{{ $li }}</li>
              @endforeach
            </ul>
          @elseif(!empty($block['p']))
            <p>{{ $block['p'] }}</p>
          @endif
        @endforeach
      </div>

      {{-- "Supported by us as free software" callout (display-only — relation field is ARCHITECTURAL) --}}
      @if(!empty($linkedProduct))
      <div class="fs-block mt-l">
        <div>
          <span class="tag-fs">Supported by us as free software</span>
          <h3>{{ $linkedProduct['name'] }}</h3>
          <p>{{ $linkedProduct['short'] }} We maintain it in the open so the argument above isn't just rhetoric — the code is there to inspect, fork and host yourself.</p>
        </div>
        <a href="/products/{{ $linkedProduct['slug'] }}" class="btn btn--primary">View the product <span class="arw" aria-hidden="true">→</span></a>
      </div>
      @endif

      {{-- Attribution --}}
      <div class="attrib mt-l">
        <b>Nikolay Petrov</b>
        <span>Founder &amp; CEO · Cybertechnology</span>
      </div>
    </div>
  </section>

  {{-- ═══ CTA ═══ --}}
  <section class="section cta-band section--dark">
    <div class="wrap stack" style="align-items:center">
      <span class="eyebrow on-dark">Get in touch</span>
      <h2>Build with a studio that means it</h2>
      <div class="cta-actions">
        <a href="/contacts" class="btn btn--light">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
