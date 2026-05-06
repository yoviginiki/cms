@php
  $items = $data['items'] ?? [];
  $style = $data['style'] ?? 'horizontal';
  $sticky = $data['sticky'] ?? true;
  $smooth = $data['smooth'] ?? true;
  $offset = $data['offset'] ?? 80;

  $navStyle = $sticky ? 'position:sticky;top:0;z-index:90;' : '';
  $isVertical = $style === 'vertical';
  $isPills = $style === 'pills';
@endphp
<nav class="anchormenu-block anchormenu-block--{{ $style }}" style="{{ $navStyle }}background:var(--color-bg, #fff);border-bottom:1px solid var(--color-border, #e5e7eb);padding:0.75rem 1.5rem;">
  <div style="display:flex;{{ $isVertical ? 'flex-direction:column;gap:0.5rem;' : 'align-items:center;gap:1.25rem;' }}">
    @foreach($items as $item)
      @php $label = $item['label'] ?? ''; $anchor = $item['anchor'] ?? '#'; @endphp
      <a href="{{ $anchor }}"
        class="anchormenu-link"
        style="font-size:0.8125rem;color:var(--color-text-muted, #64748b);text-decoration:none;transition:color 0.2s;{{ $isPills ? 'padding:0.25rem 0.75rem;border-radius:9999px;background:var(--color-bg-alt, #f8fafc);' : '' }}"
        onmouseover="this.style.color='var(--color-primary, #3b82f6)'"
        onmouseout="this.style.color='var(--color-text-muted, #64748b)'">
        {{ $label }}
      </a>
    @endforeach
  </div>
</nav>
@if($smooth)
<script>
(function(){
  document.querySelectorAll('.anchormenu-link').forEach(function(a){
    a.addEventListener('click', function(e){
      var href = a.getAttribute('href');
      if(href && href.startsWith('#')){
        e.preventDefault();
        var el = document.querySelector(href);
        if(el){
          var top = el.getBoundingClientRect().top + window.pageYOffset - {{ $offset }};
          window.scrollTo({top: top, behavior: 'smooth'});
        }
      }
    });
  });
})();
</script>
@endif
