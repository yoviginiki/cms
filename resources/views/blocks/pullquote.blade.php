@php
    $text = $data['text'] ?? '';
    $attribution = $data['attribution'] ?? '';
    $style = $data['style'] ?? 'large-text';
@endphp

<figure class="pullquote pullquote--{{ $style }}">
    <blockquote>{{ $text }}</blockquote>
    @if($attribution)
        <figcaption>{{ $attribution }}</figcaption>
    @endif
</figure>
