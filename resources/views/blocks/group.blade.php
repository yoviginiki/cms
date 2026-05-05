@php
    $tag = $data['tag'] ?? 'div';
    $allowed = ['div', 'section', 'article'];
    if (!in_array($tag, $allowed)) $tag = 'div';
@endphp
<{{ $tag }}>{!! $children !!}</{{ $tag }}>
