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
<div class="post-excerpt-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $fontSize = $cssDim($data['fontSize'] ?? '');
    $color = $cssVal($data['color'] ?? '');
    $textAlign = in_array($data['textAlign'] ?? '', ['left','center','right']) ? $data['textAlign'] : '';
    $maxLines = (int) ($data['maxLines'] ?? 0);

    // Dynamic: pull from template context
    $post = $__post ?? null;
    $excerpt = $post?->excerpt ?? '';
@endphp
@if($excerpt)
<p style="{{ $fontSize ? "font-size:{$fontSize};" : '' }}{{ $color ? "color:{$color};" : 'color:var(--color-text-muted,#666);' }}{{ $textAlign ? "text-align:{$textAlign};" : '' }}{{ $maxLines > 0 ? "display:-webkit-box;-webkit-line-clamp:{$maxLines};-webkit-box-orient:vertical;overflow:hidden;" : '' }}">{{ $excerpt }}</p>
@endif

</div>
