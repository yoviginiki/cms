@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba);
    $__customClass = BlockStyle::safeClass($__adv['customClass'] ?? '');
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="breadcrumbs-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
  $separator = $data['separator'] ?? '/';
  $showHome = $data['showHome'] ?? true;
  $homeLabel = $data['homeLabel'] ?? 'Home';
  $showCurrent = $data['showCurrent'] ?? true;
  $useSchema = $data['schema'] ?? true;

  // Build breadcrumb trail from page hierarchy
  $crumbs = [];
  if ($showHome) {
      $crumbs[] = ['label' => $homeLabel, 'url' => '/'];
  }

  // If we have a page with parent, build the chain
  if (isset($page) && $page->parent_id) {
      $ancestors = [];
      $current = $page->parent;
      while ($current) {
          array_unshift($ancestors, ['label' => $current->title, 'url' => '/' . $current->slug]);
          $current = $current->parent;
      }
      $crumbs = array_merge($crumbs, $ancestors);
  }

  if ($showCurrent && isset($page)) {
      $crumbs[] = ['label' => $page->title, 'url' => null];
  }
@endphp
<nav class="breadcrumbs-block" aria-label="Breadcrumb" style="padding:0.5rem 0;font-size:0.8125rem;">
  <ol style="list-style:none;margin:0;padding:0;display:flex;align-items:center;gap:0.375rem;flex-wrap:wrap;"@if($useSchema) itemscope itemtype="https://schema.org/BreadcrumbList"@endif>
    @foreach($crumbs as $i => $crumb)
      <li style="display:flex;align-items:center;gap:0.375rem;"@if($useSchema) itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"@endif>
        @if($i > 0)
          <span style="color:var(--color-text-muted, #9ca3af);" aria-hidden="true">{{ $separator }}</span>
        @endif
        @if($crumb['url'])
          <a href="{{ $crumb['url'] }}" style="color:var(--color-primary, #3b82f6);text-decoration:none;"@if($useSchema) itemprop="item"@endif>
            <span @if($useSchema)itemprop="name"@endif>{{ $crumb['label'] }}</span>
          </a>
        @else
          <span style="color:var(--color-text, #1e293b);"@if($useSchema) itemprop="name"@endif>{{ $crumb['label'] }}</span>
        @endif
        @if($useSchema)<meta itemprop="position" content="{{ $i + 1 }}" />@endif
      </li>
    @endforeach
  </ol>
</nav>

</div>