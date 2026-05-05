@php
    $headers = $data['headers'] ?? [];
    $rows = $data['rows'] ?? [];
    $striped = $data['striped'] ?? true;
    $compact = $data['compact'] ?? false;
    $pad = $compact ? 'padding:4px 8px;font-size:0.85em;' : 'padding:8px 12px;';
@endphp
<div class="overflow-x-auto">
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th style="{{ $pad }}text-align:left;border-bottom:2px solid #e5e7eb;font-weight:600;">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $ri => $row)
                <tr @if($striped && $ri % 2 === 1) style="background:#f9fafb;" @endif>
                    @foreach($row as $cell)
                        <td style="{{ $pad }}border-bottom:1px solid #f3f4f6;">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
