@php
  $menuId = $data['menuId'] ?? '';
  $style = $data['style'] ?? 'horizontal';
  $sticky = $data['sticky'] ?? false;
  $showLogo = $data['showLogo'] ?? false;

  $menu = null;
  if ($menuId && isset($site)) {
      $menu = \App\Models\Menu::where('site_id', $site->id)->where('id', $menuId)->first();
  }
  if (!$menu && isset($site)) {
      $menu = \App\Models\Menu::where('site_id', $site->id)->orderBy('created_at')->first();
  }
  $items = $menu
      ? $menu->items()->whereNull('parent_id')->orderBy('sort_order')
          ->with(['page:id,title,slug', 'post:id,title,slug', 'category:id,name,slug'])
          ->get()
      : collect();

  $navStyle = $sticky ? 'position:sticky;top:0;z-index:100;' : '';
  $isVertical = $style === 'vertical';
@endphp
<nav class="menu-block menu-block--{{ $style }}" style="{{ $navStyle }}background:var(--color-bg, #fff);border-bottom:1px solid var(--color-border, #e5e7eb);padding:0.75rem 1.5rem;">
  <div style="display:flex;align-items:center;{{ $isVertical ? 'flex-direction:column;gap:0.5rem;' : 'gap:1.5rem;' }}">
    @if($showLogo && isset($site))
      <a href="/" style="font-weight:700;font-size:1.1rem;color:var(--color-text, #1e293b);text-decoration:none;">{{ $site->name }}</a>
    @endif
    @foreach($items as $item)
      <a href="{{ $item->resolveUrl() }}" @if($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif style="font-size:0.875rem;color:var(--color-text-muted, #64748b);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--color-primary, #3b82f6)'" onmouseout="this.style.color='var(--color-text-muted, #64748b)'">
        {{ $item->label }}
      </a>
    @endforeach
    @if($items->isEmpty())
      <span style="font-size:0.8rem;color:#9ca3af;font-style:italic;">No menu items configured</span>
    @endif
  </div>
</nav>
