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
<div class="textdivider-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $style = $data['style'] ?? 'line';
    $customSymbol = $data['customSymbol'] ?? '';
    $width = $data['width'] ?? 'half';

    $widthMap = [
        'full' => '100%',
        'half' => '50%',
        'quarter' => '25%',
    ];

    $maxWidth = $widthMap[$width] ?? '50%';

    $symbols = [
        'dots' => '&middot;&middot;&middot;',
        'asterisks' => '* * *',
        'dinkus' => '***',
        'custom' => $customSymbol,
    ];
@endphp

@if($style === 'line')
    <hr class="text-divider text-divider--line" style="max-width: {{ $maxWidth }}; margin-left: auto; margin-right: auto; border-color: var(--color-border);">
@else
    <div class="text-divider text-divider--{{ $style }}" style="text-align: center; max-width: {{ $maxWidth }}; margin-left: auto; margin-right: auto; color: var(--color-text-muted); letter-spacing: 0.2em;">
        {!! $symbols[$style] ?? '' !!}
    </div>
@endif

</div>