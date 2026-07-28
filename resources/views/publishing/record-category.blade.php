@use('App\Support\Blocks\RecordDisplay')
{{-- Fallback category-page body — used when the collection has no
     record-archive template. Breadcrumb + subcategory links + card grid of
     the category subtree's records. --}}
@php
    $prefix = '/' . (($collection->settings['path_prefix'] ?? null) ?: $collection->slug);
    $crumbNodes = [];
    $walk = $node;
    while ($walk) {
        array_unshift($crumbNodes, $walk);
        $walk = $walk->parent_id ? \App\Models\CollectionCategoryNode::find($walk->parent_id) : null;
    }
@endphp
<section class="record-category" style="padding:2.5rem 0;">
    <nav aria-label="Breadcrumb" style="font-size:.85rem;opacity:.7;margin-bottom:1rem;">
        <a href="{{ $prefix }}/" style="color:inherit;">{{ $collection->name }}</a>
        @foreach($crumbNodes as $crumb)
            <span aria-hidden="true"> / </span>
            @if($crumb->id === $node->id)
                <span aria-current="page">{{ $crumb->name }}</span>
            @else
                <a href="{{ RecordDisplay::categoryUrl($collection, $crumb) }}" style="color:inherit;">{{ $crumb->name }}</a>
            @endif
        @endforeach
    </nav>
    <h1 style="margin:0 0 1.5rem;">{{ $node->name }}</h1>

    @if($children->isNotEmpty())
        <div class="record-category-children" style="display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:2rem;">
            @foreach($children as $child)
                <a href="{{ RecordDisplay::categoryUrl($collection, $child) }}"
                   style="padding:.45em 1em;border:1px solid var(--color-border,#e5e2dd);border-radius:999px;color:inherit;text-decoration:none;font-size:.9em;">
                    {{ $child->name }}
                </a>
            @endforeach
        </div>
    @endif

    @if($records->isEmpty())
        <p style="opacity:.6;">Nothing here yet.</p>
    @else
        <div class="record-loop-block"><div class="record-loop-items" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(240px,100%),1fr));gap:1.5rem;">
            @foreach($records as $record)
                @php $thumb = RecordDisplay::thumbUrl($site, $collection, $record); @endphp
                <article class="record-card" style="border:1px solid var(--color-border,#e5e2dd);background:var(--color-surface,#fff);overflow:hidden;">
                    @if($thumb)
                        <a href="{{ RecordDisplay::recordUrl($collection, $record) }}" style="display:block;">
                            <div style="position:relative;width:100%;padding-top:66%;overflow:hidden;">
                                <img src="{{ $thumb }}" alt="{{ $record->title }}" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                            </div>
                        </a>
                    @endif
                    <div style="padding:1rem 1.25rem;">
                        <h2 style="margin:0;font-size:1.05rem;">
                            <a href="{{ RecordDisplay::recordUrl($collection, $record) }}" style="color:inherit;text-decoration:none;">{{ $record->title }}</a>
                        </h2>
                    </div>
                </article>
            @endforeach
        </div></div>
    @endif
</section>
