{{-- Page-side Global Section embed: the referenced section's PUBLISHED tree is
     inlined by BuildPageService enrichment (static output stays self-contained;
     no runtime lookups). Renders nothing when the section is missing/unpublished. --}}
@if(!empty($data['_global_html']))
{!! $data['_global_html'] !!}
@else
<!-- global_ref: {{ empty($data['sectionId']) ? 'no section selected' : 'section not published' }} -->
@endif
