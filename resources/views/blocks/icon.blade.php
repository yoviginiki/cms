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
<div class="icon-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $name = $data['name'] ?? 'star';
    $size = $data['size'] ?? 'md';
    $color = $data['color'] ?? '';
    $background = $data['background'] ?? 'none';
    $backgroundColor = $data['backgroundColor'] ?? '';

    $sizeMap = ['sm' => '24px', 'md' => '40px', 'lg' => '56px', 'xl' => '80px'];
    $dim = $sizeMap[$size] ?? $sizeMap['md'];

    $bgStyles = '';
    if ($background === 'circle') {
        $bgStyles = "background-color:" . e($backgroundColor ?: '#e5e7eb') . ";border-radius:50%;padding:8px;";
    } elseif ($background === 'square') {
        $bgStyles = "background-color:" . e($backgroundColor ?: '#e5e7eb') . ";border-radius:8px;padding:8px;";
    }
@endphp
<div class="icon-block" style="display:inline-flex;align-items:center;justify-content:center;width:{{ $dim }};height:{{ $dim }};font-size:calc({{ $dim }} * 0.5);@if(!empty($color))color:{{ e($color) }};@endif{{ $bgStyles }}">
    <span>{{ e($name) }}</span>
</div>

</div>