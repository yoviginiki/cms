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
    $__justify = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$data['align'] ?? 'left'] ?? 'flex-start';
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="back-to-top-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $label = trim((string) ($data['label'] ?? '')) ?: 'Back to top';
    $style = $data['style'] ?? 'link';
    $look = match ($style) {
        'button' => 'padding:0.5rem 1rem;border-radius:var(--btn-radius,6px);background:var(--btn-bg,var(--color-primary,#1b6df5));color:var(--btn-color,#fff);',
        'icon' => 'width:40px;height:40px;border-radius:50%;background:var(--color-bg-alt,#f1f5f9);color:inherit;',
        default => 'color:inherit;min-height:36px;',
    };
@endphp
<div style="display:flex;justify-content:{{ $__justify }};">
    <a href="#" @if($style === 'icon') aria-label="{{ $label }}" title="{{ $label }}" @endif
       onclick="window.scrollTo({top:0,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});return false;"
       style="display:inline-flex;align-items:center;justify-content:center;gap:0.35rem;text-decoration:none;font-size:0.9rem;{{ $look }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
        @if($style !== 'icon')<span>{{ $label }}</span>@endif
    </a>
</div>
</div>
