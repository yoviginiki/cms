@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba, $data ?? []);
    $__customClass = BlockStyle::buildClasses($__adv, $__ba);
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);

    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw|auto|0)$/i', trim((string) $v)) ? trim((string) $v) : '';

    // Source: system menu or custom inline links
    $source = $data['source'] ?? 'system';
    $menuId = $data['menuId'] ?? '';
    $style = $data['style'] ?? 'horizontal';
    $sticky = $data['sticky'] ?? false;
    $showLogo = $data['showLogo'] ?? false;
    $showBorder = $data['showBorder'] ?? true;
    $borderWidth = in_array($data['borderWidth'] ?? '1px', ['0','1px','2px','3px','4px']) ? ($data['borderWidth'] ?? '1px') : '1px';
    $borderMaxWidth = preg_match('/^\d+(\.\d+)?(px|rem|em|%|vw)$/', $data['borderMaxWidth'] ?? '') ? $data['borderMaxWidth'] : '';
    // Read alignment from menu data or map from shared typography textAlign
    $rawAlign = $data['alignment'] ?? '';
    if (!$rawAlign) {
        $textAlign = $blockStyle['typography']['textAlign'] ?? ($data['__style']['typography']['textAlign'] ?? '');
        $rawAlign = match($textAlign) { 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end', default => 'space-between' };
    }
    $alignment = in_array($rawAlign, ['flex-start','center','flex-end','space-between','space-around','space-evenly']) ? $rawAlign : 'space-between';
    $mobileBreakpoint = max(0, min(1920, (int)($data['mobileBreakpoint'] ?? 768)));

    // Styling
    $bgColor = $cssVal($data['bgColor'] ?? '');
    $textColor = $cssVal($data['textColor'] ?? '');
    $hoverColor = $cssVal($data['hoverColor'] ?? '');
    $activeColor = $cssVal($data['activeColor'] ?? '');
    $borderColor = $cssVal($data['borderColor'] ?? '');
    $fontSize = $cssDim($data['fontSize'] ?? '') ?: '0.875rem';
    $fontWeight = in_array((string)($data['fontWeight'] ?? ''), ['400','500','600','700']) ? $data['fontWeight'] : '';
    $padding = $cssVal($data['padding'] ?? '') ?: '0.75rem 1.5rem';
    $itemGap = $cssDim($data['itemGap'] ?? '') ?: '1.5rem';
    $borderRadius = $cssDim($data['borderRadius'] ?? '');
    $logoSize = $cssDim($data['logoSize'] ?? '') ?: '1.1rem';

    $isVertical = $style === 'vertical';
    $isHamburger = $style === 'hamburger';

    // Resolve menu items
    $items = collect();
    $customItems = [];

    if ($source === 'custom') {
        $customItems = is_array($data['customItems'] ?? null) ? $data['customItems'] : [];
    } else {
        // System menu
        $menu = null;
        if ($menuId && isset($site)) {
            $menu = \App\Models\Menu::where('site_id', $site->id)->where('id', $menuId)->first();
        }
        if (!$menu && isset($site)) {
            $menu = \App\Models\Menu::where('site_id', $site->id)->orderBy('created_at')->first();
        }
        $eagerRelations = ['page:id,title,slug,status', 'post:id,title,slug,status', 'category:id,name,slug'];
        $items = $menu
            ? $menu->items()->whereNull('parent_id')->orderBy('sort_order')
                ->with(['children' => fn($q) => $q->orderBy('sort_order')->with([...$eagerRelations,
                    'children' => fn($q2) => $q2->orderBy('sort_order')->with($eagerRelations)
                ]), ...$eagerRelations])
                ->get()
            : collect();
    }

    // Draft filtering for system menus
    $isVisible = function($item) {
        if ($item->page_id) {
            if (!$item->page) return false;
            if (($item->page->status ?? '') !== 'published') return false;
        }
        if ($item->post_id) {
            if (!$item->post) return false;
            if (($item->post->status ?? '') !== 'published') return false;
        }
        return true;
    };

    // Determine base URL for menu links — only prefix on dynamic site preview route
    $menuBaseUrl = '';
    if (isset($site) && request()->route() && str_starts_with(request()->route()->uri(), 'sites/')) {
        $menuBaseUrl = '/sites/' . ($site->slug ?? $site->id);
    }

    // Scoped class for CSS
    $scopeClass = 'menu-' . substr(md5($__htmlId ?: uniqid()), 0, 8);

    // Computed styles
    $navBg = $bgColor ?: 'var(--color-bg,#fff)';
    $navBorder = $borderColor ?: 'var(--color-border,#e5e7eb)';
    $linkColor = $textColor ?: 'var(--color-text-muted,#64748b)';
    $linkHover = $hoverColor ?: 'var(--color-primary,#3b82f6)';
    $linkActive = $activeColor ?: $linkHover;
    $safeUrl = fn($v) => preg_match('/^(javascript|data|vbscript)\s*:/i', preg_replace('/[\x00-\x1f\x7f\s]/', '', (string) $v)) ? '#' : (string) $v;
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<style>
.{{ $scopeClass }} .menu-item{position:relative;}
.{{ $scopeClass }} .menu-top-link,.{{ $scopeClass }} .menu-custom-link{font-size:{{ $fontSize }};@if($fontWeight)font-weight:{{ $fontWeight }};@endif color:{{ $linkColor }};text-decoration:none;transition:color 0.2s,background 0.2s;}
.{{ $scopeClass }} .menu-top-link:hover,.{{ $scopeClass }} .menu-custom-link:hover{color:{{ $linkHover }};}
.{{ $scopeClass }} .menu-item .submenu{display:none;position:absolute;top:100%;left:0;min-width:180px;background:{{ $navBg }};border:1px solid {{ $navBorder }};border-radius:0.5rem;box-shadow:0 4px 16px rgba(0,0,0,0.08);padding:0.25rem 0;z-index:50;}
.{{ $scopeClass }}.menu-block--vertical .menu-item .submenu{position:static;border:none;box-shadow:none;padding-left:1rem;}
.{{ $scopeClass }} .menu-item:hover>.submenu{display:block;}
.{{ $scopeClass }} .submenu .submenu{top:0;left:100%;}
.{{ $scopeClass }} .submenu .menu-link{display:block;padding:0.5rem 1rem;font-size:{{ $fontSize }};color:{{ $linkColor }};text-decoration:none;white-space:nowrap;transition:background 0.15s,color 0.15s;}
.{{ $scopeClass }} .submenu .menu-link:hover{background:{{ $hoverColor ? $hoverColor . '1a' : 'var(--color-primary-light,#eff6ff)' }};color:{{ $linkHover }};}
.{{ $scopeClass }} .has-children>.menu-top-link::after{content:'';display:inline-block;width:0;height:0;margin-left:4px;vertical-align:middle;border-left:3px solid transparent;border-right:3px solid transparent;border-top:4px solid currentColor;}
/* Hamburger */
.{{ $scopeClass }} .menu-hamburger-btn{display:none;align-items:center;padding:0.25rem;background:none;border:none;cursor:pointer;color:{{ $linkColor }};}
.{{ $scopeClass }} .menu-hamburger-panel{display:none;flex-direction:column;gap:0.25rem;padding-top:0.75rem;border-top:1px solid {{ $navBorder }};margin-top:0.75rem;}
.{{ $scopeClass }} .menu-hamburger-panel .menu-mobile-link{display:block;padding:0.5rem 0;font-size:{{ $fontSize }};color:{{ $linkColor }};text-decoration:none;}
.{{ $scopeClass }} .menu-hamburger-panel .menu-mobile-link:hover{color:{{ $linkHover }};}
@if(!$isHamburger && $mobileBreakpoint > 0)
@media(max-width:{{ $mobileBreakpoint }}px){
.{{ $scopeClass }} .menu-desktop-links{display:none!important;}
.{{ $scopeClass }} .menu-hamburger-btn{display:flex!important;}
}
@endif
@if($isHamburger)
.{{ $scopeClass }} .menu-desktop-links{display:none!important;}
.{{ $scopeClass }} .menu-hamburger-btn{display:flex!important;}
@endif
</style>
<div class="menu-block {{ $scopeClass }} {{ $isVertical ? 'menu-block--vertical' : '' }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
<nav style="{{ $sticky ? 'position:sticky;top:0;z-index:100;' : '' }}background:{{ $navBg }};padding:{{ $padding }};{{ $borderRadius ? "border-radius:{$borderRadius};" : '' }}">
  <div style="display:flex;align-items:center;{{ $isVertical ? 'flex-direction:column;gap:0.5rem;' : "gap:{$itemGap};" }}justify-content:{{ $alignment }};">
    <div style="display:flex;align-items:center;{{ $isVertical ? 'flex-direction:column;gap:0.5rem;' : "gap:{$itemGap};" }}">
      @if($showLogo && isset($site))
        <a href="{{ $menuBaseUrl }}/" style="font-weight:700;font-size:{{ $logoSize }};color:{{ $textColor ?: 'var(--color-text,#1e293b)' }};text-decoration:none;">{{ $site->name }}</a>
      @endif

      {{-- Desktop links --}}
      <div class="menu-desktop-links" style="display:flex;align-items:center;{{ $isVertical ? 'flex-direction:column;gap:0.5rem;' : "gap:{$itemGap};" }}">
        @if($source === 'custom')
          @foreach($customItems as $ci)
            @php $ciUrl = $safeUrl($ci['url'] ?? '#'); @endphp
            <a href="{{ $ciUrl }}" class="menu-custom-link" @if(($ci['target'] ?? '_self') === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $ci['label'] ?? '' }}</a>
          @endforeach
        @else
          @foreach($items as $item)
            @if($isVisible($item))
            @php
              $visibleChildren = $item->children ? $item->children->filter($isVisible) : collect();
              $hasChildren = $visibleChildren->count() > 0;
            @endphp
            <div class="menu-item {{ $hasChildren ? 'has-children' : '' }}">
              <a href="{{ $item->resolveUrl($menuBaseUrl) }}" class="menu-top-link" @if($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>
                {{ $item->label }}
              </a>
              @if($hasChildren)
                <div class="submenu">
                  @foreach($visibleChildren as $child)
                    @php
                      $visibleGrandchildren = $child->children ? $child->children->filter($isVisible) : collect();
                      $childHasChildren = $visibleGrandchildren->count() > 0;
                    @endphp
                    <div class="menu-item {{ $childHasChildren ? 'has-children' : '' }}">
                      <a href="{{ $child->resolveUrl($menuBaseUrl) }}" class="menu-link" @if($child->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>
                        {{ $child->label }}
                      </a>
                      @if($childHasChildren)
                        <div class="submenu">
                          @foreach($visibleGrandchildren as $grandchild)
                            <a href="{{ $grandchild->resolveUrl($menuBaseUrl) }}" class="menu-link" @if($grandchild->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>
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
            @endif
          @endforeach
        @endif
        @if($source === 'system' && $items->isEmpty() && empty($customItems))
          <span style="font-size:0.8rem;color:#9ca3af;font-style:italic;">No menu items configured</span>
        @endif
      </div>
    </div>

    {{-- Hamburger button --}}
    <button class="menu-hamburger-btn" onclick="this.closest('nav').querySelector('.menu-hamburger-panel').classList.toggle('menu-open');this.querySelector('.hamburger-open').classList.toggle('hidden');this.querySelector('.hamburger-close').classList.toggle('hidden');" aria-label="Toggle menu">
      <svg class="hamburger-open" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
      <svg class="hamburger-close hidden" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>
  </div>

  {{-- Hamburger panel --}}
  <div class="menu-hamburger-panel">
    @if($source === 'custom')
      @foreach($customItems as $ci)
        @php $ciUrl = $safeUrl($ci['url'] ?? '#'); @endphp
        <a href="{{ $ciUrl }}" class="menu-mobile-link" @if(($ci['target'] ?? '_self') === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $ci['label'] ?? '' }}</a>
      @endforeach
    @else
      @foreach($items as $item)
        @if($isVisible($item))
        <a href="{{ $item->resolveUrl($menuBaseUrl) }}" class="menu-mobile-link" @if($item->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $item->label }}</a>
          @php $visibleChildren = $item->children ? $item->children->filter($isVisible) : collect(); @endphp
          @foreach($visibleChildren as $child)
            <a href="{{ $child->resolveUrl($menuBaseUrl) }}" class="menu-mobile-link" style="padding-left:1rem;font-size:calc({{ $fontSize }} - 0.0625rem);" @if($child->target === '_blank') target="_blank" rel="noopener noreferrer" @endif>{{ $child->label }}</a>
          @endforeach
        @endif
      @endforeach
    @endif
  </div>
</nav>
@if($showBorder)
<div style="border-bottom:{{ $borderWidth }} solid {{ $navBorder }};{{ $borderMaxWidth ? "max-width:{$borderMaxWidth};margin:0 auto;" : '' }}"></div>
@endif
</div>
<style>.menu-hamburger-panel.menu-open{display:flex!important;}</style>
