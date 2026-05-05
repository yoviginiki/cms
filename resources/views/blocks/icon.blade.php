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
