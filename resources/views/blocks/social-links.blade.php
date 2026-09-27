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
<div class="social-links-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $style = $data['style'] ?? 'circle';
    $__isz = $data['iconSize'] ?? null;
    $iconPx = (is_numeric($__isz) && $__isz >= 10 && $__isz <= 160)
        ? (string) (int) round($__isz)
        : (['sm' => '16', 'md' => '20', 'lg' => '26'][$data['size'] ?? 'md'] ?? '20');
    $gapPx = is_numeric($data['gap'] ?? null) ? max(0, min(80, (int) $data['gap'])) : 8;
    $color = $data['color'] ?? '';
    $showLabels = $style === 'text' || !empty($data['showLabels']);
    $box = (int) $iconPx + 16;
    $__iconBg = (string) ($data['iconBg'] ?? '');
    $__bg = $__iconBg === 'none' ? 'transparent'
        : (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,.\/%]+\))$/', $__iconBg) ? $__iconBg : 'var(--color-bg-alt,#f1f5f9)');
    $__hasBg = in_array($style, ['circle', 'square'], true) && $__iconBg !== 'none';
    $__shadow = \App\Support\Blocks\SocialIcons::SHADOWS[$data['shadow'] ?? ''] ?? null;
    $__boxShadow = $__shadow && $__hasBg ? "box-shadow:{$__shadow[0]};" : '';
    $__iconFilter = $__shadow && !$__hasBg ? "filter:{$__shadow[1]};" : '';
    $shape = match ($style) {
        'circle' => "width:{$box}px;height:{$box}px;border-radius:50%;background:{$__bg};{$__boxShadow}",
        'square' => "width:{$box}px;height:{$box}px;border-radius:var(--border-radius-sm,6px);background:{$__bg};{$__boxShadow}",
        default => 'min-width:36px;min-height:36px;',
    };
    $links = array_values(array_filter(array_map(function ($l) {
        $network = $l['network'] ?? 'website';
        $href = \App\Support\Blocks\SocialIcons::href($network, (string) ($l['url'] ?? ''));
        return $href ? [
            'network' => $network,
            'href' => $href,
            'label' => trim((string) ($l['label'] ?? '')) ?: (\App\Support\Blocks\SocialIcons::NETWORKS[$network] ?? 'Link'),
            'external' => str_starts_with($href, 'http'),
            'icon' => preg_match('#^(/|https?://)#i', (string) ($l['icon'] ?? '')) ? (string) $l['icon'] : '',
        ] : null;
    }, is_array($data['links'] ?? null) ? $data['links'] : [])));
@endphp
@php
    $__title = \App\Support\Blocks\SocialIcons::title($data['title'] ?? null);
    $__inline = $__title && in_array($__title['position'], ['before', 'after'], true);
    $__titleAlign = $__title['align'] ?? ['flex-start' => 'left', 'center' => 'center', 'flex-end' => 'right'][$__justify];
@endphp
@if($__title && count($links))
<div class="social-links-wrap" style="display:flex;{{ $__inline ? 'flex-wrap:wrap;align-items:center;justify-content:' . $__justify : 'flex-direction:column;align-items:stretch' }};gap:{{ $__title['gap'] }}px;">
@endif
@if($__title && count($links) && in_array($__title['position'], ['above', 'before'], true))
<{{ $__title['tag'] }} class="social-links-title" style="{{ $__inline ? '' : 'text-align:' . $__titleAlign . ';' }}{{ $__title['style'] }}">{{ $__title['text'] }}</{{ $__title['tag'] }}>
@endif
@if(count($links))
<ul style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:{{ $gapPx }}px;align-items:center;justify-content:{{ $__justify }};">
    @foreach($links as $l)
    <li style="margin:0;">
        <a href="{{ $l['href'] }}" @if($l['external']) target="_blank" rel="noopener me" @endif @if(!$showLabels) aria-label="{{ $l['label'] }}" title="{{ $l['label'] }}" @endif
           style="display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;{{ $style === 'text' ? 'min-height:36px;' : $shape }}color:{{ $color !== '' ? e($color) : 'inherit' }};text-decoration:none;transition:opacity .2s;"
           onmouseover="this.style.opacity=.7" onmouseout="this.style.opacity=1">
            @if($style !== 'text')
                @if($l['icon'] !== '')<img src="{{ $l['icon'] }}" alt="" width="{{ $iconPx }}" height="{{ $iconPx }}" style="width:{{ $iconPx }}px;height:{{ $iconPx }}px;object-fit:contain;display:block;{{ $__iconFilter }}">
                @else<span style="display:inline-flex;{{ $__iconFilter }}">{!! \App\Support\Blocks\SocialIcons::svg($l['network'], $iconPx) !!}</span>@endif
            @endif
            @if($showLabels)<span style="font-size:0.9rem;">{{ $l['label'] }}</span>@endif
        </a>
    </li>
    @endforeach
</ul>
@endif
@if($__title && count($links) && in_array($__title['position'], ['below', 'after'], true))
<{{ $__title['tag'] }} class="social-links-title" style="{{ $__inline ? '' : 'text-align:' . $__titleAlign . ';' }}{{ $__title['style'] }}">{{ $__title['text'] }}</{{ $__title['tag'] }}>
@endif
@if($__title && count($links))
</div>
@endif
</div>
