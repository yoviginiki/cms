@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba);
    $__customClass = BlockStyle::safeClass($__adv['customClass'] ?? '');
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="heading-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $level = in_array($data['level'] ?? 'h2', ['h1','h2','h3','h4','h5','h6']) ? ($data['level'] ?? 'h2') : 'h2';
    $sizeMap = ['h1' => 'var(--font-size-3xl,2rem)', 'h2' => 'var(--font-size-2xl,1.5rem)', 'h3' => 'var(--font-size-xl,1.25rem)', 'h4' => 'var(--font-size-lg,1.125rem)', 'h5' => 'var(--font-size-base,1rem)', 'h6' => 'var(--font-size-sm,0.875rem)'];
    $headingSize = $sizeMap[$level] ?? $sizeMap['h2'];
@endphp
<{{ $level }} style="font-size:{{ $headingSize }};font-weight:var(--font-weight-bold,700);font-family:var(--font-heading,inherit);line-height:var(--line-height-tight,1.25);color:var(--color-text,#1e293b);">{{ $data['text'] ?? '' }}</{{ $level }}>

</div>