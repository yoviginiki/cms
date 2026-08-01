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
<div class="list-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $items = $data['items'] ?? [];
    $listType = $data['listType'] ?? 'bullet';
@endphp

@if($listType === 'numbered')
    <ol>
        @foreach($items as $i => $item)
            <li{!! sp_editable($__blockId ?? '', "items.{$i}", 'text') !!}>{{ $item }}</li>
        @endforeach
    </ol>
@elseif($listType === 'checklist')
    <ul class="checklist" style="list-style: none; padding-left: 0;">
        @foreach($items as $i => $item)
            <li style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" disabled>
                <span{!! sp_editable($__blockId ?? '', "items.{$i}", 'text') !!}>{!! BlockStyle::safeInlineHtml($item) !!}</span>
            </li>
        @endforeach
    </ul>
@else
    <ul>
        @foreach($items as $i => $item)
            <li{!! sp_editable($__blockId ?? '', "items.{$i}", 'text') !!}>{{ $item }}</li>
        @endforeach
    </ul>
@endif

</div>