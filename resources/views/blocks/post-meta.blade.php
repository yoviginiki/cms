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
<div class="post-meta-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $showDate = $data['showDate'] ?? true;
    $showAuthor = $data['showAuthor'] ?? true;
    $showCategory = $data['showCategory'] ?? true;
    $separator = $data['separator'] ?? '·';
    $textAlign = in_array($data['textAlign'] ?? '', ['left','center','right']) ? $data['textAlign'] : '';

    // Dynamic: pull from template context
    $post = $__post ?? null;
    $parts = [];
    if ($showDate && $post && $post->published_at) {
        $parts[] = '<time datetime="' . $post->published_at->toIso8601String() . '">' . $post->published_at->format('M j, Y') . '</time>';
    }
    if ($showAuthor && $post && $post->author) {
        $parts[] = '<span class="post-meta-author">' . e($post->author->name ?? '') . '</span>';
    }
    if ($showCategory && $post && $post->category) {
        $catUrl = '/' . e($post->category->slug);
        $parts[] = '<a href="' . $catUrl . '" class="post-meta-category">' . e($post->category->name) . '</a>';
    }
    $safeSep = e($separator);
@endphp
<div class="post-meta" style="font-size:var(--font-size-sm,0.875rem);color:var(--color-text-muted,#999);{{ $textAlign ? "text-align:{$textAlign};" : '' }}">
    {!! implode(" <span class=\"post-meta-sep\"> {$safeSep} </span> ", $parts) !!}
</div>

</div>
