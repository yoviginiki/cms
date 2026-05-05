{{-- Standard layout: header + max-width content + footer --}}
@if($layout->supports['header'] ?? true)
    @include('layouts.partials.site-header')
@endif

<main style="max-width: {{ $layout->supports['maxWidthValue'] ?? '48rem' }}; margin: 0 auto; padding: 0 1.5rem;">
    {!! $blocksHtml !!}
</main>

@if($layout->supports['footer'] ?? true)
    @include('layouts.partials.site-footer')
@endif
