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
    $iconPx = ['sm' => '16', 'md' => '20', 'lg' => '26'][$data['size'] ?? 'md'] ?? '20';
    $color = $data['color'] ?? '';
    $showLabels = $style === 'text' || !empty($data['showLabels']);
    $box = (int) $iconPx + 16;
    $shape = match ($style) {
        'circle' => "width:{$box}px;height:{$box}px;border-radius:50%;background:var(--color-bg-alt,#f1f5f9);",
        'square' => "width:{$box}px;height:{$box}px;border-radius:var(--border-radius-sm,6px);background:var(--color-bg-alt,#f1f5f9);",
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
        ] : null;
    }, is_array($data['links'] ?? null) ? $data['links'] : [])));
@endphp
@if(count($links))
<ul style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;justify-content:{{ $__justify }};">
    @foreach($links as $l)
    <li style="margin:0;">
        <a href="{{ $l['href'] }}" @if($l['external']) target="_blank" rel="noopener me" @endif @if(!$showLabels) aria-label="{{ $l['label'] }}" title="{{ $l['label'] }}" @endif
           style="display:inline-flex;align-items:center;justify-content:center;gap:0.4rem;{{ $style === 'text' ? 'min-height:36px;' : $shape }}color:{{ $color !== '' ? e($color) : 'inherit' }};text-decoration:none;transition:opacity .2s;"
           onmouseover="this.style.opacity=.7" onmouseout="this.style.opacity=1">
            @if($style !== 'text'){!! \App\Support\Blocks\SocialIcons::svg($l['network'], $iconPx) !!}@endif
            @if($showLabels)<span style="font-size:0.9rem;">{{ $l['label'] }}</span>@endif
        </a>
    </li>
    @endforeach
</ul>
@endif
</div>
