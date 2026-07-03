{{-- Page-side slider embed: the referenced slider's PUBLISHED tree is inlined
     by BuildPageService enrichment (static output stays self-contained; no
     runtime lookups). Renders nothing when the slider is missing/unpublished. --}}
@if(!empty($data['_slider_html']))
{!! $data['_slider_html'] !!}
@else
<!-- slider_ref: {{ empty($data['sliderId']) ? 'no slider selected' : 'slider not published' }} -->
@endif
