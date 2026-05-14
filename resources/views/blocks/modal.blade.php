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
<div class="modal-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $triggerText = $data['triggerText'] ?? 'Open';
    $title = $data['title'] ?? '';
    $size = $data['size'] ?? 'md';
    $sizeClass = match($size) { 'sm' => 'modal-box max-w-sm', 'lg' => 'modal-box max-w-lg', default => 'modal-box' };
    $modalId = 'modal-' . ($data['_block_id'] ?? uniqid());
@endphp
<div class="modal-block" style="margin-bottom: var(--space-6, 1.5rem);">
    <button class="btn btn-primary" onclick="this.nextElementSibling.showModal()">{{ e($triggerText) }}</button>
    <dialog id="{{ $modalId }}" class="modal">
        <div class="{{ $sizeClass }}">
            @if($title)
                <h3 class="text-lg font-bold mb-4">{{ e($title) }}</h3>
            @endif
            <div class="modal-content">
                {!! $children ?? '' !!}
            </div>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-sm">Close</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</div>

</div>