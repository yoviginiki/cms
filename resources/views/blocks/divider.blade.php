@php
    $style = $data['style'] ?? 'solid';
    $color = $data['color'] ?? '#ccc';
    $thickness = $data['thickness'] ?? '1px';
    $width = $data['width'] ?? '100%';
    $alignment = $data['alignment'] ?? 'center';
    $marginMap = ['left' => '0 auto 0 0', 'center' => '0 auto', 'right' => '0 0 0 auto'];
    $margin = $marginMap[$alignment] ?? '0 auto';
@endphp
<hr class="divider-block" style="border: none; border-top: {{ $thickness }} {{ $style }} {{ $color }}; width: {{ $width }}; margin: {{ $margin }};">
