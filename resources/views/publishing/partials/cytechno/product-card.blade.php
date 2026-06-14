{{-- Cytechno — Product card partial (option a: display surface)
     Variables: $product = { slug, name, cat, short, price, priceLabel, img }
--}}
<a class="card" href="/products/{{ $product['slug'] }}">
  <div class="ph r43" data-label="{{ $product['img'] ?? strtoupper('PRODUCT · ' . ($product['name'] ?? '')) }}"></div>
  <div class="body">
    <span class="cat">{{ $product['cat'] ?? '' }}</span>
    <h3>{{ $product['name'] ?? '' }}</h3>
    <p>{{ $product['short'] ?? '' }}</p>
    <div class="meta row" style="justify-content:space-between;align-items:flex-end">
      <span class="price{{ ($product['price'] ?? 0) === 0 ? ' free' : '' }}">{{ $product['priceLabel'] ?? 'Free' }}@if(($product['price'] ?? 0) !== 0)<small> one-time</small>@endif</span>
      <span class="txtlink">View <span class="arw" aria-hidden="true">→</span></span>
    </div>
  </div>
</a>
