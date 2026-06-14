{{-- Cytechno — Single Blog Post (Slice 5)
     Variables: $post = { slug, title, date, author, read, body[] }
                $relatedPosts = [ { slug, date, title, excerpt } ... ]
     $post.body is an array of { p: text } | { h: heading } | { q: quote } | { ul: [items] }
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'blog'])

@section('content')
<div class="fadein">

  {{-- ═══ Centered hero ═══ --}}
  <section class="page-hero">
    <div class="wrap" style="max-width:860px;margin-inline:auto">
      <a href="/blog" class="txtlink" style="margin-bottom:18px"><span class="arw">←</span> All articles</a>
      <h1 style="font-size:clamp(2.1rem,5vw,4rem)">{{ $post['title'] ?? 'Post' }}</h1>
      <div class="wrap-flex mt-m" style="gap:28px">
        <span class="cat" style="color:var(--ink-3)">{{ $post['date'] ?? '' }}</span>
        <span class="cat" style="color:var(--ink-3)">{{ $post['author'] ?? '' }}</span>
        <span class="cat" style="color:var(--ink-3)">{{ $post['read'] ?? '' }} read</span>
      </div>
    </div>
  </section>

  {{-- ═══ Prose body ═══ --}}
  <section class="section">
    <div class="wrap" style="max-width:860px;margin-inline:auto">
      <div class="prose">
        @foreach(($post['body'] ?? []) as $block)
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

      {{-- Author attribution --}}
      <div class="attrib mt-l">
        <b>{{ $post['author'] ?? 'Nikolay Petrov' }}</b>
        <span>Founder &amp; CEO · Cybertechnology</span>
      </div>
    </div>
  </section>

  {{-- ═══ Related posts ═══ --}}
  @if(!empty($relatedPosts))
  <section class="section section--alt">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">Related</span>
        <h2 class="section-title">Keep reading</h2>
      </div>
      <div class="artlist">
        @foreach($relatedPosts as $r)
        <a class="artrow" href="/blog/{{ $r['slug'] }}">
          <span class="date">{{ $r['date'] }}</span>
          <div>
            <h3>{{ $r['title'] }}</h3>
            <p>{{ $r['excerpt'] ?? '' }}</p>
          </div>
          <span class="arw" aria-hidden="true">→</span>
        </a>
        @endforeach
      </div>
    </div>
  </section>
  @endif

</div>
@endsection
