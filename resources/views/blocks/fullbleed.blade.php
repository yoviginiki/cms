@php
    $src = $data['src'] ?? '';
    $alt = $data['alt'] ?? '';
    $overlayText = $data['overlayText'] ?? '';
    $overlayPosition = $data['overlayPosition'] ?? 'center';
    $scrimOpacity = $data['scrimOpacity'] ?? 0.4;
    $minHeight = $data['minHeight'] ?? '60vh';

    $positionStyles = [
        'center' => 'align-items:center;justify-content:center;text-align:center;',
        'bottom-left' => 'align-items:flex-end;justify-content:flex-start;text-align:left;',
        'bottom-right' => 'align-items:flex-end;justify-content:flex-end;text-align:right;',
    ];
    $posStyle = $positionStyles[$overlayPosition] ?? $positionStyles['center'];
@endphp
<section class="fullbleed-block" style="position:relative;min-height:{{ e($minHeight) }};@if(!empty($src))background-image:url('{{ e($src) }}');background-size:cover;background-position:center;@endif">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,{{ $scrimOpacity }});"></div>
    @if(!empty($overlayText))
        <div style="position:relative;z-index:1;display:flex;{{ $posStyle }}min-height:{{ e($minHeight) }};padding:2rem;">
            <p style="color:#fff;font-size:1.5rem;font-weight:700;max-width:42rem;">{{ e($overlayText) }}</p>
        </div>
    @endif
</section>
