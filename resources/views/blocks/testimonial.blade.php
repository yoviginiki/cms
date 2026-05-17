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
<div class="testimonial-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $items = $data['items'] ?? [];
    $layout = $data['layout'] ?? 'single';
    $gridStyle = $layout === 'grid' ? 'display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;' : '';

    $cardBgColor = $cssVal($data['cardBgColor'] ?? '');
    $cardBorderColor = $cssVal($data['cardBorderColor'] ?? '') ?: 'var(--color-border,#e2e8f0)';
    $cardRadius = is_array($data['cardBorderRadius'] ?? null)
        ? implode(' ', array_map(fn($k) => $cssDim($data['cardBorderRadius'][$k] ?? '') ?: '0.75rem', ['topLeft','topRight','bottomRight','bottomLeft']))
        : ($cssDim($data['cardBorderRadius'] ?? '') ?: '0.75rem');
    $cardShadow = BlockStyle::buildShadowCss($data['cardShadowMode'] ?? 'preset', $data['cardShadow'] ?? '', is_array($data['cardShadowCustom'] ?? null) ? $data['cardShadowCustom'] : null);
    $quoteColor = $cssVal($data['quoteColor'] ?? '') ?: 'var(--color-text,#1e293b)';
    $authorColor = $cssVal($data['authorColor'] ?? '');
    $tsShadowPresets = ['sm' => '0 1px 2px rgba(0,0,0,0.15)', 'md' => '0 2px 4px rgba(0,0,0,0.25)', 'lg' => '0 4px 8px rgba(0,0,0,0.4)', 'outline' => '-1px -1px 0 rgba(0,0,0,0.3),1px -1px 0 rgba(0,0,0,0.3),-1px 1px 0 rgba(0,0,0,0.3),1px 1px 0 rgba(0,0,0,0.3)', 'glow' => '0 0 10px rgba(255,255,255,0.8),0 0 20px rgba(255,255,255,0.4)'];
    $textShadow = $tsShadowPresets[$data['textShadow'] ?? ''] ?? '';
@endphp
<div style="{{ $gridStyle }}">
    @foreach($items as $item)
        <blockquote style="border:1px solid {{ $cardBorderColor }};border-radius:{{ $cardRadius }};padding:1.5rem;margin-bottom:{{ $layout === 'grid' ? '0' : '1rem' }};{{ $cardBgColor ? "background-color:{$cardBgColor};" : '' }}{{ $cardShadow ? "box-shadow:{$cardShadow};" : '' }}">
            <p style="font-style:italic;color:{{ $quoteColor }};margin-bottom:1rem;{{ $textShadow ? "text-shadow:{$textShadow};" : '' }}">&ldquo;{{ $item['quote'] ?? '' }}&rdquo;</p>
            <div style="display:flex;align-items:center;gap:0.75rem;">
                @if(!empty($item['avatar']))
                    <img src="{{ $item['avatar'] }}" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" />
                @endif
                <div>
                    <div style="font-weight:600;{{ $authorColor ? "color:{$authorColor};" : '' }}{{ $textShadow ? "text-shadow:{$textShadow};" : '' }}">{{ $item['author'] ?? '' }}</div>
                    <div style="color:var(--color-text-muted,#64748b);font-size:0.875rem;">{{ $item['role'] ?? '' }}</div>
                </div>
            </div>
        </blockquote>
    @endforeach
</div>

</div>