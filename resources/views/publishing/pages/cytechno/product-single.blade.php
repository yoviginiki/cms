{{-- Cytechno — Single Product (Slice 8)
     Variables: $product = { slug, name, cat, short, price, priceLabel, img, description, features[], cta{label,kind} }
                $otherProducts = [ { slug, name, cat, short, price, priceLabel, img } ... ]
     NOTE: features[] and cta are block data (option a). Typed fields = option (b), gated.
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'products'])

@section('content')
<div class="fadein">

  {{-- ═══ Product hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <a href="/products" class="txtlink" style="margin-bottom:18px"><span class="arw">←</span> All products</a>
      <div class="grid cols-2" style="gap:clamp(28px,4vw,56px);align-items:center;margin-top:10px">
        <div>
          <span class="cat">{{ $product['cat'] ?? '' }}</span>
          <h1 style="margin-top:12px">{{ $product['name'] ?? 'Product' }}</h1>
          <p class="lead mt-m" style="max-width:46ch">{{ $product['short'] ?? '' }}</p>
          <div class="row gap-m mt-l" style="gap:24px;align-items:center;flex-wrap:wrap">
            <span class="price{{ ($product['price'] ?? 0) === 0 ? ' free' : '' }}" style="font-size:2.4rem">{{ $product['priceLabel'] ?? 'Free' }}@if(($product['price'] ?? 0) !== 0)<small> one-time</small>@endif</span>
            <a href="/contacts" class="btn btn--primary">{{ $product['cta']['label'] ?? 'Get in touch' }} <span class="arw" aria-hidden="true">→</span></a>
          </div>
        </div>
        <div class="ph r43" data-label="{{ $product['img'] ?? strtoupper('PRODUCT · ' . ($product['name'] ?? '')) }}"></div>
      </div>
    </div>
  </section>

  {{-- ═══ Description + structured features ═══ --}}
  <section class="section">
    <div class="wrap grid cols-2" style="gap:clamp(34px,5vw,72px);align-items:start">
      <div>
        <div class="section-head">
          <span class="eyebrow">Description</span>
          <h2 class="section-title">What it is</h2>
        </div>
        <p class="lead">{{ $product['description'] ?? '' }}</p>
      </div>
      <div>
        <div class="section-head">
          <span class="eyebrow">Structured features</span>
          <h2 class="section-title">Specification</h2>
        </div>
        {{-- DOGFOOD: typed key/value pairs — currently block data (option a) --}}
        @if(!empty($product['features']))
        <ul class="feature-list">
          @foreach($product['features'] as $feature)
          <li><b>{{ $feature[0] ?? '' }}</b><span>{{ $feature[1] ?? '' }}</span></li>
          @endforeach
        </ul>
        @endif
      </div>
    </div>
  </section>

  {{-- ═══ Typed CTA band ═══ --}}
  <section class="section section--alt">
    <div class="wrap cta-band stack" style="align-items:center;text-align:center">
      <span class="eyebrow">{{ $product['cta']['kind'] ?? 'Get started' }}</span>
      @php
        $ctaKind = $product['cta']['kind'] ?? '';
        $ctaHeadline = match(true) {
          str_contains($ctaKind, 'repo') => 'Inspect, fork and host it yourself',
          str_contains($ctaKind, 'Try') => 'Put it in your pipeline today',
          default => 'Tell us about your platform',
        };
      @endphp
      <h2 style="font-family:'Barlow Condensed',sans-serif;font-weight:700;text-transform:uppercase;font-size:clamp(1.8rem,4vw,3.2rem);line-height:.98;margin:16px 0 26px;max-width:18ch">{{ $ctaHeadline }}</h2>
      <a href="/contacts" class="btn btn--primary">{{ $product['cta']['label'] ?? 'Get in touch' }} <span class="arw" aria-hidden="true">→</span></a>
    </div>
  </section>

  {{-- ═══ More products ═══ --}}
  @if(!empty($otherProducts))
  <section class="section">
    <div class="wrap">
      <div class="section-head">
        <span class="eyebrow">More products</span>
        <h2 class="section-title">From the catalogue</h2>
        <a href="/products" class="btn btn--ghost" style="align-self:flex-start">All products <span class="arw" aria-hidden="true">→</span></a>
      </div>
      <div class="grid cols-3">
        @foreach($otherProducts as $op)
          @include('publishing.partials.cytechno.product-card', ['product' => $op])
        @endforeach
      </div>
    </div>
  </section>
  @endif

</div>
@endsection
