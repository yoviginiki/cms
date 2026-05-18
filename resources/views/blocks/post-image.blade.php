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
<div class="post-image-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $size = $data['size'] ?? 'full';
    $aspectRatio = $data['aspectRatio'] ?? '';
    $borderRadius = $cssDim($data['borderRadius'] ?? '');
    $objectFit = in_array($data['objectFit'] ?? 'cover', ['cover','contain','fill','none']) ? ($data['objectFit'] ?? 'cover') : 'cover';

    // Dynamic: pull from template context
    $post = $__post ?? null;
    $imageUrl = match($size) {
        'thumbnail' => $post?->thumbnail ?? $post?->featured_image ?? '',
        default => $post?->featured_image ?? '',
    };
@endphp
@if($imageUrl)
    <img src="{{ $imageUrl }}" alt="{{ $post?->title ?? '' }}" loading="lazy"
        style="width:100%;height:auto;object-fit:{{ $objectFit }};{{ $borderRadius ? "border-radius:{$borderRadius};" : '' }}{{ $aspectRatio ? "aspect-ratio:{$aspectRatio};" : '' }}" />
@endif

</div>
