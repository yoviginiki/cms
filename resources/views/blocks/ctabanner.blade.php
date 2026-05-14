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
<div class="ctabanner-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $heading = $data['heading'] ?? 'Ready to get started?';
    $text = $data['text'] ?? '';
    $buttonText = $data['buttonText'] ?? 'Get started';
    $buttonUrl = $data['buttonUrl'] ?? '#';
    $bgStyle = $data['backgroundStyle'] ?? 'solid';
    $bgColor = $data['backgroundColor'] ?? '#3b82f6';
    $bgImage = $data['backgroundImage'] ?? '';

    $inlineStyle = match($bgStyle) {
        'gradient' => "background: linear-gradient(135deg, {$bgColor}, {$bgColor}cc);",
        'image' => "background-image: url('" . e($bgImage) . "'); background-size: cover; background-position: center;",
        default => "background-color: {$bgColor};",
    };
@endphp
<div class="ctabanner-block" style="{{ $inlineStyle }} padding: 3rem 1.5rem; text-align: center; color: #fff; margin-bottom: var(--space-6, 1.5rem);">
    <div style="max-width: 640px; margin: 0 auto;">
        <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.75rem;">{{ e($heading) }}</h2>
        @if($text)
            <p style="font-size: 1rem; opacity: 0.9; margin-bottom: 1.5rem;">{{ e($text) }}</p>
        @endif
        <a href="{{ e($buttonUrl) }}" class="btn btn-primary" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4); color: #fff; padding: 0.75rem 2rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600;">
            {{ e($buttonText) }}
        </a>
    </div>
</div>

</div>