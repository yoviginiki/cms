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
<div class="category-header-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $tag = in_array($data['titleTag'] ?? 'h1', ['h1','h2','h3']) ? ($data['titleTag'] ?? 'h1') : 'h1';
    $titleSize = $cssDim($data['titleSize'] ?? '');
    $titleColor = $cssVal($data['titleColor'] ?? '');
    $textAlign = in_array($data['textAlign'] ?? 'center', ['left','center','right']) ? $data['textAlign'] : 'center';
    $showDescription = $data['showDescription'] ?? true;
    $showPostCount = $data['showPostCount'] ?? false;

    $category = $__category ?? null;
    $archivePostCount = $__archivePostCount ?? 0;
@endphp
<div style="text-align:{{ $textAlign }};">
    <{{ $tag }} style="{{ $titleSize ? "font-size:{$titleSize};" : '' }}{{ $titleColor ? "color:{$titleColor};" : '' }}">{{ $category?->name ?? 'Archive' }}</{{ $tag }}>
    @if($showDescription && $category?->description)
        <p style="color:var(--color-text-muted,#666);margin-top:0.5rem;">{{ $category->description }}</p>
    @endif
    @if($showPostCount)
        <p style="font-size:var(--font-size-sm,0.875rem);color:var(--color-text-muted,#999);margin-top:0.25rem;">{{ $archivePostCount }} {{ $archivePostCount === 1 ? 'post' : 'posts' }}</p>
    @endif
</div>

</div>
