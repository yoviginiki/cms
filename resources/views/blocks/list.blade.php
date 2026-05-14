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
<div class="list-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $items = $data['items'] ?? [];
    $listType = $data['listType'] ?? 'bullet';
@endphp

@if($listType === 'numbered')
    <ol>
        @foreach($items as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ol>
@elseif($listType === 'checklist')
    <ul class="checklist" style="list-style: none; padding-left: 0;">
        @foreach($items as $item)
            <li style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" disabled>
                <span>{{ $item }}</span>
            </li>
        @endforeach
    </ul>
@else
    <ul>
        @foreach($items as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
@endif

</div>