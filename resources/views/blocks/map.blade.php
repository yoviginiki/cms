@php
    $lat = $data['lat'] ?? 42.6977;
    $lng = $data['lng'] ?? 23.3219;
    $zoom = $data['zoom'] ?? 13;
    $markerLabel = $data['markerLabel'] ?? '';
    $height = $data['height'] ?? '400px';
@endphp
<div style="height:{{ $height }};background:#e5e7eb;display:flex;align-items:center;justify-content:center;border-radius:0.5rem;">
    <a href="https://maps.google.com/?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener noreferrer" style="color:#3b82f6;text-decoration:underline;">
        @if($markerLabel)
            {{ $markerLabel }} &mdash;
        @endif
        View on Google Maps ({{ $lat }}, {{ $lng }})
    </a>
</div>
