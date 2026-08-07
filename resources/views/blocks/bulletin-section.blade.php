@php
    $title = trim((string) ($data['title'] ?? ''));
@endphp
<section class="bulletin-section">
    @if ($title !== '')
        <h2 class="bulletin-section__title">{{ $title }}</h2>
    @endif
    <div class="bulletin-section__events">
        {!! $children ?? '' !!}
    </div>
</section>
