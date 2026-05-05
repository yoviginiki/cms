@php
    $presets = ['sm' => '1rem', 'md' => '2rem', 'lg' => '4rem', 'xl' => '6rem'];
    $h = $data['height'] ?? 'md';
    $height = $presets[$h] ?? $h;
@endphp
<div class="spacer-block" style="height: {{ $height }};" aria-hidden="true"></div>
