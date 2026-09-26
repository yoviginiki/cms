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
<div class="site-identity-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $s = $site->settings ?? [];
    $logo = trim((string) ($s['logo_url'] ?? ''));
    $name = (string) ($site->name ?? '');
    $tagline = trim((string) ($s['tagline'] ?? ''));
    $show = $data['show'] ?? 'auto';
    if ($show === 'auto') {
        $show = $logo !== '' ? (!empty($s['logo_show_name']) ? 'both' : 'logo') : 'name';
    }
    if ($logo === '' && $show === 'logo') { $show = 'name'; }
    $size = $data['size'] ?? 'md';
    $logoH = ['sm' => '32px', 'md' => '44px', 'lg' => '60px'][$size] ?? '44px';
    $nameSize = ['sm' => '1.1rem', 'md' => '1.4rem', 'lg' => '1.9rem'][$size] ?? '1.4rem';
    $locale = $__locale ?? \App\Domain\Publishing\Services\LocalePaths::defaultLanguage($site);
    $home = '/' . \App\Domain\Publishing\Services\LocalePaths::prefix($site, $locale);
    $linkHome = ($data['linkHome'] ?? true) !== false;
    $tag = $linkHome ? 'a' : 'div';
@endphp
<{{ $tag }} @if($linkHome) href="{{ $home }}" @endif style="display:flex;align-items:center;gap:0.75rem;justify-content:{{ $__justify }};color:inherit;text-decoration:none;">
    @if(in_array($show, ['logo', 'both'], true) && $logo !== '')
        <img src="{{ $logo }}" alt="{{ $show === 'logo' ? $name : '' }}" style="height:{{ $logoH }};width:auto;display:block;">
    @endif
    @if(in_array($show, ['name', 'both'], true))
        <span style="display:flex;flex-direction:column;line-height:1.2;">
            <span style="font-family:var(--font-heading,inherit);font-weight:var(--heading-weight,700);font-size:{{ $nameSize }};">{{ $name }}</span>
            @if(!empty($data['showTagline']) && $tagline !== '')
                <span style="font-size:0.85rem;opacity:.75;">{{ $tagline }}</span>
            @endif
        </span>
    @endif
</{{ $tag }}>
</div>
