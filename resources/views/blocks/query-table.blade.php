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
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);

    $result = null;
    $collection = null;
    if (!empty($data['queryId'])) {
        try {
            $__query = \App\Models\SavedQuery::find($data['queryId']);
            if ($__query) {
                $result = app(\App\Domain\Collections\Queries\QueryRunner::class)->run($__query);
                $collection = $__query->sourceCollection();
            }
        } catch (\Throwable $e) {
            logger()->warning("query-table render failed ({$data['queryId']}): {$e->getMessage()}");
        }
    }

    $maxRows = max(1, min(100, (int) ($data['maxRows'] ?? 25)));
    $showHeader = $data['showHeader'] ?? true;
    $striped = $data['striped'] ?? true;
    $cellStyle = 'padding:.55rem .9rem;border-bottom:1px solid var(--color-border,#e5e2dd);text-align:left;';
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="query-table-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@if(!$result)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a saved query for this table.</p>
@elseif($result['type'] === 'table')
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:.92rem;">
        @if($showHeader)
        <thead><tr>
            @foreach($result['columns'] as $col)
                <th style="{{ $cellStyle }}font-weight:600;border-bottom-width:2px;">{{ $col['label'] }}</th>
            @endforeach
        </tr></thead>
        @endif
        <tbody>
        @foreach(array_slice($result['rows'], 0, $maxRows) as $i => $row)
            <tr @if($striped && $i % 2 === 1) style="background:var(--color-surface-alt,rgba(0,0,0,.025));" @endif>
                @foreach($result['columns'] as $col)
                    <td style="{{ $cellStyle }}">{{ is_numeric($row[$col['key']] ?? null) && floor((float) $row[$col['key']]) != $row[$col['key']] ? number_format((float) $row[$col['key']], 2) : ($row[$col['key']] ?? '—') }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
@elseif($result['type'] === 'records')
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:.92rem;">
        @if($showHeader)<thead><tr><th style="{{ $cellStyle }}font-weight:600;border-bottom-width:2px;">Title</th></tr></thead>@endif
        <tbody>
        @foreach(collect($result['rows'])->take($maxRows) as $i => $record)
            <tr @if($striped && $i % 2 === 1) style="background:var(--color-surface-alt,rgba(0,0,0,.025));" @endif>
                <td style="{{ $cellStyle }}">
                    @if($collection)<a href="{{ RecordDisplay::recordUrl($collection, $record) }}" style="color:inherit;">{{ $record->title }}</a>@else{{ $record->title }}@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
@else
    <div class="query-stat-value" style="font-size:2.4rem;font-weight:700;">{{ number_format((float) $result['value'], is_int($result['value']) ? 0 : 2) }}</div>
@endif
</div>
