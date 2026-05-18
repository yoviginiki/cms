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
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="stickysidebar-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $side = $data['sidebarSide'] ?? 'right';
    $sidebarW = $data['sidebarWidth'] ?? '300px';
    $gap = $data['gap'] ?? '32px';
    $offset = $data['stickyOffset'] ?? '80px';
@endphp
<div class="stickysidebar-block" style="display: flex; gap: {{ $gap }};{{ $side === 'left' ? ' flex-direction: row-reverse;' : '' }}">
    <div style="flex: 1; min-width: 0;">
        {!! $children !!}
    </div>
    <aside style="width: {{ $sidebarW }}; flex-shrink: 0; position: sticky; top: {{ $offset }}; align-self: flex-start;">
        {{-- Sidebar content placed via children or slots --}}
    </aside>
</div>

</div>