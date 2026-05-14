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
<div class="categorylist-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $style = $data['style'] ?? 'links';
    $showCount = $data['showCount'] ?? true;
    $parentOnly = $data['parentOnly'] ?? false;
    // $categories would be populated at build time
    $categories = $categories ?? [];
@endphp
@if($style === 'badges')
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
        @foreach($categories as $cat)
            <a href="{{ $cat['url'] ?? '#' }}" style="display:inline-flex;align-items:center;gap:4px;padding:0.375rem 0.75rem;background:#f3f4f6;border-radius:9999px;font-size:0.875rem;color:inherit;text-decoration:none;">
                {{ $cat['name'] ?? '' }}
                @if($showCount)<span style="color:var(--color-text-muted,#9ca3af);">({{ $cat['count'] ?? 0 }})</span>@endif
            </a>
        @endforeach
    </div>
@elseif($style === 'cards')
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;">
        @foreach($categories as $cat)
            <a href="{{ $cat['url'] ?? '#' }}" style="border:1px solid var(--color-border,#e2e8f0);border-radius:var(--border-radius-md,0.5rem);padding:1rem;text-align:center;color:inherit;text-decoration:none;">
                <div style="font-weight:600;">{{ $cat['name'] ?? '' }}</div>
                @if($showCount)<div style="font-size:0.75rem;color:var(--color-text-muted,#9ca3af);">{{ $cat['count'] ?? 0 }} posts</div>@endif
            </a>
        @endforeach
    </div>
@else
    <ul style="list-style:none;padding:0;margin:0;">
        @foreach($categories as $cat)
            <li style="display:flex;justify-content:space-between;padding:0.375rem 0;border-bottom:1px solid #f3f4f6;">
                <a href="{{ $cat['url'] ?? '#' }}" style="color:#3b82f6;text-decoration:none;">{{ $cat['name'] ?? '' }}</a>
                @if($showCount)<span style="color:var(--color-text-muted,#9ca3af);">({{ $cat['count'] ?? 0 }})</span>@endif
            </li>
        @endforeach
    </ul>
@endif

</div>