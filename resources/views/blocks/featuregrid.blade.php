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
<div class="featuregrid-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $items = $data['items'] ?? [];
    $columns = $data['columns'] ?? 3;
    $style = $data['style'] ?? 'icon-top';
    $gap = $cssDim($data['gap'] ?? '') ?: '1.5rem';
    $flexDir = $style === 'icon-left' ? 'row' : 'column';

    // Card styling
    $cardBgColor = $cssVal($data['cardBgColor'] ?? '');
    $cardBorderColor = $cssVal($data['cardBorderColor'] ?? '') ?: 'var(--color-border,#e2e8f0)';
    $cardBorderWidth = $cssDim($data['cardBorderWidth'] ?? '') ?: '1px';
    $cardRadius = is_array($data['cardBorderRadius'] ?? null)
        ? implode(' ', array_map(fn($k) => $cssDim($data['cardBorderRadius'][$k] ?? '') ?: '0.5rem', ['topLeft','topRight','bottomRight','bottomLeft']))
        : ($cssDim($data['cardBorderRadius'] ?? '') ?: 'var(--border-radius-md,0.5rem)');
    $cardPadding = $cssDim($data['cardPadding'] ?? '') ?: '1.5rem';
    $cardShadow = BlockStyle::buildShadowCss(
        $data['cardShadowMode'] ?? 'preset',
        $data['cardShadow'] ?? '',
        is_array($data['cardShadowCustom'] ?? null) ? $data['cardShadowCustom'] : null
    );

    // Typography
    $titleColor = $cssVal($data['titleColor'] ?? '');
    $descColor = $cssVal($data['descColor'] ?? '') ?: 'var(--color-text-muted,#64748b)';
    $iconSize = $cssDim($data['iconSize'] ?? '') ?: '1.5rem';
    $iconColor = $cssVal($data['iconColor'] ?? '');
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:{{ $gap }};">
    @foreach($items as $item)
        <div style="display:flex;flex-direction:{{ $flexDir }};align-items:{{ $style === 'icon-left' ? 'flex-start' : 'center' }};gap:0.75rem;padding:{{ $cardPadding }};border:{{ $cardBorderWidth }} solid {{ $cardBorderColor }};border-radius:{{ $cardRadius }};text-align:{{ $style === 'icon-left' ? 'left' : 'center' }};{{ $cardBgColor ? "background-color:{$cardBgColor};" : '' }}{{ $cardShadow ? "box-shadow:{$cardShadow};" : '' }}">
            <div style="font-size:{{ $iconSize }};line-height:1;{{ $iconColor ? "color:{$iconColor};" : '' }}">{{ $item['icon'] ?? '' }}</div>
            <div>
                <div style="font-weight:600;margin-bottom:0.25rem;{{ $titleColor ? "color:{$titleColor};" : '' }}">{{ $item['title'] ?? '' }}</div>
                <div style="color:{{ $descColor }};font-size:0.875rem;">{{ $item['description'] ?? '' }}</div>
            </div>
        </div>
    @endforeach
</div>

</div>