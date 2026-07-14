@use('App\Support\Blocks\BlockStyle')
@use('App\Support\Blocks\RecordDisplay')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba, $data ?? []);
    $__customClass = BlockStyle::buildClasses($__adv, $__ba);
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%)$/i', trim((string) $v)) ? trim((string) $v) : '';

    // Collection: explicit on the block, else the archive context's.
    $collection = null;
    if (!empty($data['collectionId'])) {
        $collection = \App\Models\ContentCollection::find($data['collectionId']);
    } elseif (isset($__collection)) {
        $collection = $__collection;
    }

    $limit = max(1, min(100, (int) ($data['limit'] ?? 12)));

    // Inside a record-archive template the paginated context is authoritative;
    // anywhere else the loop runs its own query.
    $records = collect();
    if (isset($__archiveRecords) && (!isset($__collection) || !$collection || $__collection?->id === $collection->id)) {
        $records = collect($__archiveRecords)->take($limit);
        $collection = $collection ?: ($__collection ?? null);
    } elseif ($collection) {
        $query = \App\Models\Record::where('collection_id', $collection->id)
            ->where('status', 'published')
            ->with('relationsOut.toRecord');

        // Filter/sort keys must exist in the validated schema — never raw SQL from block data.
        $filterKey = (string) ($data['filterField'] ?? '');
        if ($filterKey !== '' && $collection->field($filterKey) && ($data['filterValue'] ?? '') !== '') {
            $filterField = $collection->field($filterKey);
            $filterValue = (string) $data['filterValue'];
            if ($filterField['type'] === 'boolean') {
                $query->whereField($filterKey, filter_var($filterValue, FILTER_VALIDATE_BOOLEAN));
            } elseif ($filterField['type'] === 'multi_select') {
                $query->whereRaw("data->? @> ?::jsonb", [$filterKey, json_encode($filterValue)]);
            } else {
                $query->whereField($filterKey, $filterValue);
            }
        }

        $sortKey = (string) ($data['sortField'] ?? '');
        $direction = ($data['sortDirection'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        if (in_array($sortKey, ['created_at', 'published_at', 'title', 'position', 'updated_at'], true)) {
            $query->orderBy($sortKey, $direction);
        } elseif ($sortKey !== '' && ($sf = $collection->field($sortKey)) && !in_array($sf['type'], ['relation','gallery','rich_text'], true)) {
            $accessor = in_array($sf['type'], ['number', 'price'], true) ? "data->'{$sf['key']}'" : "data->>'{$sf['key']}'";
            $query->orderByRaw("{$accessor} {$direction} NULLS LAST");
        } else {
            $query->orderByDesc('published_at');
        }

        $records = $query->limit($limit)->get();
    }

    $layout = in_array($data['layout'] ?? 'cards', ['cards','list','grid']) ? ($data['layout'] ?? 'cards') : 'cards';
    $columns = max(1, min(6, (int) ($data['columns'] ?? 3)));
    $gap = $cssDim($data['gap'] ?? '') ?: '1.5rem';
    $showImage = $data['showImage'] ?? true;
    $imageField = (string) ($data['imageField'] ?? '');
    $cardFields = array_values(array_filter((array) ($data['cardFields'] ?? []), fn($k) => is_string($k) && $collection?->field($k)));
    $linkTo = $data['linkToRecord'] ?? true;

    $gridStyle = $layout === 'list'
        ? "display:flex;flex-direction:column;gap:{$gap};"
        : "display:grid;grid-template-columns:repeat(auto-fill,minmax(min(" . intval(720 / $columns) . "px,100%),1fr));gap:{$gap};";
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="record-loop-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@if(!$collection)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a collection for this record loop.</p>
@elseif($records->isEmpty())
    <p style="opacity:.6;">Nothing here yet.</p>
@else
    <div class="record-loop-items" style="{{ $gridStyle }}">
        @foreach($records as $record)
            @php
                $url = RecordDisplay::recordUrl($collection, $record);
                $thumb = null;
                if ($showImage) {
                    $imgKey = $imageField !== '' && $collection->field($imageField) ? $imageField : null;
                    $thumb = $imgKey
                        ? RecordDisplay::assetUrl($site, is_string($record->data[$imgKey] ?? null) ? $record->data[$imgKey] : null)
                        : RecordDisplay::thumbUrl($site, $collection, $record);
                }
            @endphp
            <article class="record-card" style="{{ $layout === 'list' ? 'display:flex;gap:1rem;align-items:flex-start;' : '' }}border:1px solid var(--color-border,#e5e2dd);background:var(--color-surface,#fff);overflow:hidden;">
                @if($thumb)
                    <a @if($linkTo) href="{{ $url }}" @endif style="display:block;{{ $layout === 'list' ? 'flex:0 0 160px;' : '' }}">
                        <div style="position:relative;width:100%;padding-top:{{ $layout === 'list' ? '100%' : '66%' }};overflow:hidden;">
                            <img src="{{ $thumb }}" alt="{{ $record->title }}" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                        </div>
                    </a>
                @endif
                <div class="record-card-body" style="padding:1rem 1.25rem;">
                    <h3 style="margin:0 0 .4rem;font-size:1.05rem;">
                        @if($linkTo)<a href="{{ $url }}" style="color:inherit;text-decoration:none;">{{ $record->title }}</a>@else{{ $record->title }}@endif
                    </h3>
                    @foreach($cardFields as $fk)
                        @php $vh = RecordDisplay::display($site, $collection, $record, $fk); @endphp
                        @if($vh !== '')
                            <div class="record-card-field record-card-field-{{ $fk }}" style="font-size:.9rem;margin:.15rem 0;color:var(--color-text-muted,#6b6864);">{!! $vh !!}</div>
                        @endif
                    @endforeach
                </div>
            </article>
        @endforeach
    </div>
@endif
</div>
