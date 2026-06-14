{{-- Cytechno — Products listing (Slice 8)
     Variables: $products = [ { slug, name, cat, short, price, priceLabel, img } ... ]
     NOTE: Price sort/filter is BLOCKED — requires option (b) structured content type.
           Toolbar rendered as static display only.
--}}
@extends('publishing.layouts.cytechno', ['currentSection' => 'products'])

@section('content')
<div class="fadein">

  {{-- ═══ Page hero ═══ --}}
  <section class="page-hero">
    <div class="wrap">
      <span class="eyebrow">Products</span>
      <h1>The tools beneath our platforms — released and supported</h1>
      <p class="lead mt-m" style="max-width:54ch">Open-source infrastructure and fixed-scope product offerings. This catalogue grows over time; some tools are free software, others are commercial.</p>
    </div>
  </section>

  {{-- ═══ Toolbar + grid ═══ --}}
  <section class="section">
    <div class="wrap">
      {{-- Toolbar — STATIC DISPLAY ONLY
           Price sort/filter requires a queryable typed field (option b).
           Controls shown for visual parity; functionality gated behind Track 0. --}}
      <div class="toolbar">
        <div class="group">
          <label>Price</label>
          <div class="seg">
            <span class="on" style="display:inline-block;border:0;background:var(--ink);color:#fff;padding:9px 15px;font-size:.66rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase">All</span>
            <span style="display:inline-block;border:0;background:#fff;padding:9px 15px;font-size:.66rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-2);border-right:1px solid var(--line-strong)">Free</span>
            <span style="display:inline-block;background:#fff;padding:9px 15px;font-size:.66rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-2)">Paid</span>
          </div>
        </div>
        <div class="group">
          <label>Sort by</label>
          <div class="seg">
            <span class="on" style="display:inline-block;border:0;background:var(--ink);color:#fff;padding:9px 15px;font-size:.66rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase">Featured</span>
            <span style="display:inline-block;border:0;background:#fff;padding:9px 15px;font-size:.66rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-2);border-right:1px solid var(--line-strong)">Price ↑</span>
            <span style="display:inline-block;background:#fff;padding:9px 15px;font-size:.66rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-2)">Price ↓</span>
          </div>
        </div>
        <span class="cat" style="color:var(--ink-3)">{{ count($products ?? []) }} products</span>
      </div>

      <div class="grid cols-3 mt-m">
        @foreach(($products ?? []) as $product)
          @include('publishing.partials.cytechno.product-card', ['product' => $product])
        @endforeach
      </div>

      <p class="muted mt-l" style="font-size:.82rem;max-width:60ch;border-top:1px solid var(--line);padding-top:18px">
        <strong>Note —</strong> sorting and filtering by <em>price</em> requires price to be a queryable typed
        field, not free-text inside a block. This is the structured-content gap gated behind Track 0.
      </p>
    </div>
  </section>

  {{-- ═══ CTA ═══ --}}
  <section class="section cta-band section--dark">
    <div class="wrap stack" style="align-items:center">
      <span class="eyebrow on-dark">Need something built?</span>
      <h2>We also build to order</h2>
      <div class="cta-actions">
        <a href="/contacts" class="btn btn--light">Start a project <span class="arw" aria-hidden="true">→</span></a>
        <a href="/portfolio" class="btn btn--light">View our work <span class="arw" aria-hidden="true">→</span></a>
      </div>
    </div>
  </section>

</div>
@endsection
