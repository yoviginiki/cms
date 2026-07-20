@use('App\Support\Blocks\RecordDisplay')
{{-- Fallback record detail body — used when the collection has no
     record-single template. Title, hero image, then every field with a value
     as a definition list. Wrapped in publishing.layout by the publisher. --}}
@php
    $titleField = $collection->titleField();
    $imageKey = RecordDisplay::firstImageField($collection);
    $heroSrc = $imageKey ? RecordDisplay::assetUrl($site, is_string($record->data[$imageKey] ?? null) ? $record->data[$imageKey] : null) : null;
    $ancestors = $ancestors ?? [];
    $children = $children ?? collect();
@endphp
<article class="record-single" style="padding:2.5rem 0;">
    <nav style="font-size:.85rem;margin-bottom:1.5rem;opacity:.7;" aria-label="Breadcrumb">
        <a href="/{{ RecordDisplay::pathPrefix($collection) }}/" style="color:inherit;">{{ $collection->name }}</a>
        @foreach($ancestors as $ancestor)
            <span aria-hidden="true"> / </span><a href="{{ RecordDisplay::recordUrl($collection, $ancestor) }}" style="color:inherit;">{{ $ancestor->title }}</a>
        @endforeach
        @if($ancestors !== [])
            <span aria-hidden="true"> / </span><span aria-current="page">{{ $record->title }}</span>
        @endif
    </nav>
    <h1 style="margin:0 0 1.5rem;">{{ $record->title }}</h1>
    @if($heroSrc)
        <img src="{{ $heroSrc }}" alt="{{ $record->title }}" style="max-width:100%;height:auto;margin-bottom:2rem;display:block;">
    @endif
    <dl style="display:grid;grid-template-columns:minmax(120px,max-content) 1fr;gap:.6rem 1.5rem;margin:0;">
        @foreach($collection->fields() as $field)
            @continue($field['key'] === $titleField || $field['key'] === $imageKey)
            @php $valueHtml = RecordDisplay::display($site, $collection, $record, $field['key']); @endphp
            @if($valueHtml !== '')
                <dt style="font-weight:600;">{{ $field['label'] }}</dt>
                <dd style="margin:0;">{!! $valueHtml !!}</dd>
            @endif
        @endforeach
    </dl>
    @if($children->isNotEmpty())
        <nav class="record-children" aria-label="Subcategories" style="margin-top:2.5rem;">
            <h2 style="font-size:1.1rem;margin:0 0 .8rem;">In {{ $record->title }}</h2>
            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.6rem;">
                @foreach($children as $child)
                    <li><a href="{{ RecordDisplay::recordUrl($collection, $child) }}" style="display:inline-block;padding:.45rem .9rem;border:1px solid var(--color-border,#ddd);color:inherit;text-decoration:none;">{{ $child->title }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif
</article>
