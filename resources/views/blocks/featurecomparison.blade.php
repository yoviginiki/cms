@php
    $plans = $data['plans'] ?? [];
    $features = $data['features'] ?? [];
@endphp
<div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
        <thead>
            <tr>
                <th style="text-align:left;padding:0.75rem;border-bottom:2px solid #e5e7eb;color:#6b7280;font-weight:500;">Feature</th>
                @foreach($plans as $plan)
                    <th style="text-align:center;padding:0.75rem;border-bottom:2px solid #e5e7eb;">
                        <div style="font-weight:600;color:#1f2937;">{{ $plan['name'] ?? '' }}</div>
                        <div style="font-size:0.75rem;color:#6b7280;">{{ $plan['price'] ?? '' }}</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($features as $fi => $feat)
                <tr style="{{ $fi % 2 === 0 ? 'background:#f9fafb;' : '' }}">
                    <td style="padding:0.75rem;color:#374151;">{{ $feat['name'] ?? '' }}</td>
                    @foreach(($feat['values'] ?? []) as $val)
                        <td style="text-align:center;padding:0.75rem;">
                            @if(is_bool($val))
                                @if($val)
                                    <span style="color:#22c55e;font-weight:700;">&#10003;</span>
                                @else
                                    <span style="color:#d1d5db;font-weight:700;">&#10005;</span>
                                @endif
                            @else
                                <span style="color:#374151;">{{ $val }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
