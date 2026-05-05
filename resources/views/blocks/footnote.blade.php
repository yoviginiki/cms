@php
    $content = $data['content'] ?? '';
    $marker = $data['marker'] ?? '*';
@endphp

<aside class="footnote">
    <sup>{{ $marker }}</sup> {!! $content !!}
</aside>
