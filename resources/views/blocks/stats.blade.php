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
<div class="stats-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
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

    $valueColor = $cssVal($data['valueColor'] ?? '');
    $labelColor = $cssVal($data['labelColor'] ?? '') ?: 'var(--color-text-muted,#64748b)';
    $valueFontSize = $cssDim($data['valueFontSize'] ?? '') ?: '2.5rem';
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:{{ $gap }};text-align:center;">
    @foreach($items as $item)
        <div style="padding:1.5rem;border:1px solid {{ $cardBorderColor }};border-radius:{{ $cardRadius }};{{ $cardBgColor ? "background-color:{$cardBgColor};" : '' }}{{ $cardShadow ? "box-shadow:{$cardShadow};" : '' }}">
            <div style="font-size:{{ $valueFontSize }};font-weight:700;line-height:1;{{ $valueColor ? "color:{$valueColor};" : '' }}">{{ $item['prefix'] ?? '' }}{{ $item['value'] ?? '' }}{{ $item['suffix'] ?? '' }}</div>
            <div style="color:{{ $labelColor }};font-size:0.875rem;margin-top:0.5rem;">{{ $item['label'] ?? '' }}</div>
        </div>
    @endforeach
</div>

</div>