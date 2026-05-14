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
<div class="accordion-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
<div class="accordion-block" style="margin-bottom: 1.5rem;">
    @foreach(($data['items'] ?? []) as $item)
        <details style="border: 1px solid #e5e7eb; border-radius: 0.5rem; margin-bottom: 0.5rem; overflow: hidden;">
            <summary style="padding: 1rem 1.25rem; cursor: pointer; font-weight: 500; background: #f9fafb; list-style: none; display: flex; align-items: center; justify-content: space-between;">
                {{ $item['title'] ?? '' }}
                <span style="transition: transform 0.2s;">&#9660;</span>
            </summary>
            <div style="padding: 1rem 1.25rem; border-top: 1px solid #e5e7eb;">
                {!! $item['content'] ?? '' !!}
            </div>
        </details>
    @endforeach
</div>

</div>