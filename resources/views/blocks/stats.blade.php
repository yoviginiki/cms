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
<div class="stats-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $items = $data['items'] ?? [];
    $columns = $data['columns'] ?? 3;
    $gap = $cssDim($data['gap'] ?? '') ?: '1.5rem';

    $cardBgColor = $cssVal($data['cardBgColor'] ?? '');
    $cardBorderColor = $cssVal($data['cardBorderColor'] ?? '') ?: 'var(--color-border,#e2e8f0)';
    $cardRadius = is_array($data['cardBorderRadius'] ?? null)
        ? implode(' ', array_map(fn($k) => $cssDim($data['cardBorderRadius'][$k] ?? '') ?: '0.5rem', ['topLeft','topRight','bottomRight','bottomLeft']))
        : ($cssDim($data['cardBorderRadius'] ?? '') ?: 'var(--border-radius-md,0.5rem)');
    $cardShadow = BlockStyle::buildShadowCss($data['cardShadowMode'] ?? 'preset', $data['cardShadow'] ?? '', is_array($data['cardShadowCustom'] ?? null) ? $data['cardShadowCustom'] : null);

    $textAlign = in_array($data['textAlign'] ?? 'center', ['left','center','right']) ? ($data['textAlign'] ?? 'center') : 'center';
    $valueColor = $cssVal($data['valueColor'] ?? '') ?: 'var(--color-primary,#3b82f6)';
    $labelColor = $cssVal($data['labelColor'] ?? '') ?: 'var(--color-text-muted,#64748b)';
    $valueFontSize = $cssDim($data['valueFontSize'] ?? '') ?: '2.5rem';
    $tsShadowPresets = ['sm' => '0 1px 2px rgba(0,0,0,0.15)', 'md' => '0 2px 4px rgba(0,0,0,0.25)', 'lg' => '0 4px 8px rgba(0,0,0,0.4)', 'outline' => '-1px -1px 0 rgba(0,0,0,0.3),1px -1px 0 rgba(0,0,0,0.3),-1px 1px 0 rgba(0,0,0,0.3),1px 1px 0 rgba(0,0,0,0.3)', 'glow' => '0 0 10px rgba(255,255,255,0.8),0 0 20px rgba(255,255,255,0.4)'];
    $textShadow = $tsShadowPresets[$data['textShadow'] ?? ''] ?? '';
    // Plain (borderless) tiles — number + label with no card frame, as in many
    // builder hero/trust bands.
    $plain = !empty($data['plain']);
    $cellStyle = $plain
        ? 'padding:0;'
        : "padding:1.5rem;border:1px solid {$cardBorderColor};border-radius:{$cardRadius};" . ($cardBgColor ? "background-color:{$cardBgColor};" : '') . ($cardShadow ? "box-shadow:{$cardShadow};" : '');
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:{{ $gap }};text-align:{{ $textAlign }};">
    @foreach($items as $i => $item)
        @php $countTo = preg_match('/^\d[\d\s.,]*$/', trim((string) ($item['value'] ?? ''))) === 1 ? trim((string) $item['value']) : null; @endphp
        <div style="{{ $cellStyle }}">
            <div style="font-family:var(--font-heading,inherit);font-size:{{ $valueFontSize }};font-weight:700;line-height:1;color:{{ $valueColor }};{{ $textShadow ? "text-shadow:{$textShadow};" : '' }}">{{ $item['prefix'] ?? '' }}<span{!! sp_editable($__blockId ?? '', "items.{$i}.value", 'text') !!} @if($countTo) data-countup="{{ $countTo }}" @endif>{{ $item['value'] ?? '' }}</span>{{ $item['suffix'] ?? '' }}</div>
            <div{!! sp_editable($__blockId ?? '', "items.{$i}.label", 'text') !!} style="color:{{ $labelColor }};font-size:0.875rem;margin-top:0.5rem;{{ $textShadow ? "text-shadow:{$textShadow};" : '' }}">{{ $item['label'] ?? '' }}</div>
        </div>
    @endforeach
</div>

@once
<script>
/* stats count-up: numbers rise from 0 when the block scrolls into view */
(function(){
  if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var els = document.querySelectorAll('[data-countup]');
  if (!els.length || !('IntersectionObserver' in window)) return;
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if (!e.isIntersecting) return;
      io.unobserve(e.target);
      var raw = e.target.getAttribute('data-countup');
      var target = parseFloat(raw.replace(/[\s,]/g, ''));
      if (!isFinite(target)) return;
      var decimals = (raw.split('.')[1] || '').length;
      var t0 = null, dur = 1400;
      function step(ts){
        if (t0 === null) t0 = ts;
        var p = Math.min(1, (ts - t0) / dur);
        var eased = 1 - Math.pow(1 - p, 3);
        e.target.textContent = (target * eased).toFixed(decimals);
        if (p < 1) requestAnimationFrame(step); else e.target.textContent = raw;
      }
      requestAnimationFrame(step);
    });
  }, {threshold: 0.4});
  els.forEach(function(el){ io.observe(el); });
})();
</script>
@endonce

</div>