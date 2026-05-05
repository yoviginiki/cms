@php
    $content = $data['content'] ?? '';
    $columns = $data['columns'] ?? 2;
    $columnGap = $data['columnGap'] ?? '40px';
    $columnRule = $data['columnRule'] ?? false;
@endphp

<div style="column-count: {{ $columns }}; column-gap: {{ $columnGap }};{{ $columnRule ? ' column-rule: 1px solid var(--color-border);' : '' }}">
    {!! $content !!}
</div>
