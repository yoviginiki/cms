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
<div class="copyright-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $year = (int) date('Y');
    $start = (int) ($data['startYear'] ?? 0);
    $years = ($start > 0 && $start < $year) ? "{$start}–{$year}" : (string) $year;
    $text = trim((string) ($data['text'] ?? '')) ?: '© {year} {site}. All rights reserved.';
    $text = strtr($text, ['{year}' => $years, '{site}' => (string) ($site->name ?? ''), '{start}' => $start > 0 ? (string) $start : '']);
    $align = in_array($data['align'] ?? 'left', ['left', 'center', 'right'], true) ? ($data['align'] ?? 'left') : 'left';
@endphp
<p style="margin:0;font-size:0.875rem;text-align:{{ $align }};">{{ $text }}</p>
</div>
