@use('App\Support\Blocks\RecordDisplay')
{{-- Fallback archive body — used when the collection has no record-archive
     template. Card grid of the page's records + static pagination. --}}
<section class="record-archive" style="padding:2.5rem 0;">
    <h1 style="margin:0 0 2rem;">{{ $collection->name }}</h1>
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
        @if($totalPages > 1)
            <nav class="record-archive-pagination" aria-label="Pagination" style="display:flex;gap:.5rem;justify-content:center;margin-top:2.5rem;font-size:.95rem;">
                @if($currentPage > 1)
                    <a href="{{ $currentPage === 2 ? $baseUrl . '/' : $baseUrl . '/page/' . ($currentPage - 1) . '/' }}" style="padding:.4rem .8rem;border:1px solid var(--color-border,#ccc);color:inherit;text-decoration:none;">&larr; Prev</a>
                @endif
                @for($i = 1; $i <= $totalPages; $i++)
                    <a href="{{ $i === 1 ? $baseUrl . '/' : $baseUrl . '/page/' . $i . '/' }}"
                       @if($i === $currentPage) aria-current="page" @endif
                       style="padding:.4rem .8rem;border:1px solid var(--color-border,#ccc);color:inherit;text-decoration:none;{{ $i === $currentPage ? 'background:var(--color-text,#222);color:var(--color-surface,#fff);' : '' }}">{{ $i }}</a>
                @endfor
                @if($currentPage < $totalPages)
                    <a href="{{ $baseUrl . '/page/' . ($currentPage + 1) . '/' }}" style="padding:.4rem .8rem;border:1px solid var(--color-border,#ccc);color:inherit;text-decoration:none;">Next &rarr;</a>
                @endif
            </nav>
        @endif
    @endif
</section>
