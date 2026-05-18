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
<div class="post-title-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $cssVal = fn($v) => preg_replace('/[^a-zA-Z0-9#(),.\s%\/\-]/', '', (string) $v);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';

    $tag = in_array($data['tag'] ?? 'h1', ['h1','h2','h3','h4','h5','h6']) ? ($data['tag'] ?? 'h1') : 'h1';
    $fontSize = $cssDim($data['fontSize'] ?? '');
    $fontWeight = in_array((string)($data['fontWeight'] ?? ''), ['400','500','600','700','800']) ? $data['fontWeight'] : '';
    $color = $cssVal($data['color'] ?? '');
    $textAlign = in_array($data['textAlign'] ?? '', ['left','center','right']) ? $data['textAlign'] : '';
    $tsShadowPresets = ['sm' => '0 1px 2px rgba(0,0,0,0.15)', 'md' => '0 2px 4px rgba(0,0,0,0.25)', 'lg' => '0 4px 8px rgba(0,0,0,0.4)', 'outline' => '-1px -1px 0 rgba(0,0,0,0.3),1px -1px 0 rgba(0,0,0,0.3),-1px 1px 0 rgba(0,0,0,0.3),1px 1px 0 rgba(0,0,0,0.3)', 'glow' => '0 0 10px rgba(255,255,255,0.8),0 0 20px rgba(255,255,255,0.4)'];
    $textShadow = $tsShadowPresets[$data['textShadow'] ?? ''] ?? '';

    // Dynamic: pull from template context
    $post = $__post ?? null;
    $postTitle = $post?->title ?? 'Post Title';
@endphp
<{{ $tag }} style="{{ $fontSize ? "font-size:{$fontSize};" : '' }}{{ $fontWeight ? "font-weight:{$fontWeight};" : '' }}{{ $color ? "color:{$color};" : '' }}{{ $textAlign ? "text-align:{$textAlign};" : '' }}{{ $textShadow ? "text-shadow:{$textShadow};" : '' }}">{{ $postTitle }}</{{ $tag }}>

</div>
