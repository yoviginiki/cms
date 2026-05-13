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
          ->with(['children' => fn($q) => $q->orderBy('sort_order')->with(['page:id,title,slug', 'post:id,title,slug', 'category:id,name,slug',
              'children' => fn($q2) => $q2->orderBy('sort_order')->with(['page:id,title,slug', 'post:id,title,slug', 'category:id,name,slug'])
          ]), 'page:id,title,slug', 'post:id,title,slug', 'category:id,name,slug'])
          ->get()
      : collect();

  $navStyle = $sticky ? 'position:sticky;top:0;z-index:100;' : '';
  $isVertical = $style === 'vertical';
@endphp
<style>
.menu-block .menu-item{position:relative;}
.menu-block .menu-item .submenu{display:none;position:absolute;top:100%;left:0;min-width:180px;background:var(--color-bg,#fff);border:1px solid var(--color-border,#e5e7eb);border-radius:0.5rem;box-shadow:0 4px 16px rgba(0,0,0,0.08);padding:0.25rem 0;z-index:50;}
.menu-block--vertical .menu-item .submenu{position:static;border:none;box-shadow:none;padding-left:1rem;}
.menu-block .menu-item:hover>.submenu{display:block;}
.menu-block .submenu .submenu{top:0;left:100%;}
.menu-block .submenu .menu-link{display:block;padding:0.5rem 1rem;font-size:0.8125rem;color:var(--color-text-muted,#64748b);text-decoration:none;white-space:nowrap;transition:background 0.15s,color 0.15s;}
.menu-block .submenu .menu-link:hover{background:var(--color-primary-light,#eff6ff);color:var(--color-primary,#3b82f6);}
.menu-block .has-children>.menu-top-link::after{content:'';display:inline-block;width:0;height:0;margin-left:4px;vertical-align:middle;border-left:3px solid transparent;border-right:3px solid transparent;border-top:4px solid currentColor;}
</style>
<nav class="menu-block menu-block--{{ $style }}" style="{{ $navStyle }}background:var(--color-bg, #fff);border-bottom:1px solid var(--color-border, #e5e7eb);padding:0.75rem 1.5rem;">
  <div style="display:flex;align-items:center;{{ $isVertical ? 'flex-direction:column;gap:0.5rem;' : 'gap:1.5rem;' }}">
    @if($showLogo && isset($site))
      <a href="/" style="font-weight:700;font-size:1.1rem;color:var(--color-text, #1e293b);text-decoration:none;">{{ $site->name }}</a>
    @endif
    @foreach($items as $item)
      @php $hasChildren = $item->children && $item->children->count() > 0; @endphp
      <div class="menu-item {{ $hasChildren ? 'has-children' : '' }}">
        <a href="{{ $item->resolveUrl() }}" class="menu-top-link" @if($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif style="font-size:0.875rem;color:var(--color-text-muted, #64748b);text-decoration:none;transition:color 0.2s;" onmouseover="this.style.color='var(--color-primary, #3b82f6)'" onmouseout="this.style.color='var(--color-text-muted, #64748b)'">
          {{ $item->label }}
        </a>
        @if($hasChildren)
          <div class="submenu">
            @foreach($item->children as $child)
              @php $childHasChildren = $child->children && $child->children->count() > 0; @endphp
              <div class="menu-item {{ $childHasChildren ? 'has-children' : '' }}">
                <a href="{{ $child->resolveUrl() }}" class="menu-link" @if($child->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>
                  {{ $child->label }}
                </a>
                @if($childHasChildren)
                  <div class="submenu">
                    @foreach($child->children as $grandchild)
                      <a href="{{ $grandchild->resolveUrl() }}" class="menu-link" @if($grandchild->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>
                        {{ $grandchild->label }}
                      </a>
                    @endforeach
                  </div>
                @endif
              </div>
            @endforeach
          </div>
        @endif
      </div>
    @endforeach
    @if($items->isEmpty())
      <span style="font-size:0.8rem;color:#9ca3af;font-style:italic;">No menu items configured</span>
    @endif
  </div>
</nav>
