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
