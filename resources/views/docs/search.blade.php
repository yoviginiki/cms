<h1>Search</h1>
@if($query === '')
    <p>Type a word or phrase — every word must appear in the document. Works in English and Bulgarian.</p>
@elseif(empty($results))
    <p>No documents contain <strong>{{ $query }}</strong>. Try fewer or shorter words.</p>
@else
    <p class="docs-search-count">{{ count($results) }} {{ count($results) === 1 ? 'document' : 'documents' }} for <strong>{{ $query }}</strong></p>
    @foreach($results as $r)
        <div class="docs-result">
            <a class="docs-result-title" href="/docs/{{ $r['slug'] }}?hl={{ urlencode($query) }}">{{ $r['title'] }}</a>
            <span class="docs-result-file">{{ $r['slug'] }}.md</span>
            @foreach($r['hits'] as $hit)
                <a class="docs-hit" href="/docs/{{ $r['slug'] }}?hl={{ urlencode($query) }}{{ $hit['anchor'] ? '#' . $hit['anchor'] : '' }}">
                    @if($hit['heading'])<span class="docs-hit-heading">{{ $hit['heading'] }}</span>@endif
                    <span class="docs-hit-text">{!! $hit['html'] !!}</span>
                </a>
            @endforeach
        </div>
    @endforeach
@endif
