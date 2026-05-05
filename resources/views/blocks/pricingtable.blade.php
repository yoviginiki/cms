@php
    $plans = $data['plans'] ?? [];
    $columns = $data['columns'] ?? 3;
@endphp
<div style="display:grid;grid-template-columns:repeat({{ $columns }},1fr);gap:1.5rem;">
    @foreach($plans as $plan)
        <div style="border:{{ ($plan['highlighted'] ?? false) ? '2px solid #3b82f6' : '1px solid #e5e7eb' }};border-radius:0.75rem;padding:2rem;text-align:center;{{ ($plan['highlighted'] ?? false) ? 'box-shadow:0 4px 12px rgba(0,0,0,0.1);' : '' }}">
            <div style="font-weight:600;font-size:1.1rem;margin-bottom:0.5rem;">{{ $plan['name'] ?? '' }}</div>
            <div style="font-size:2rem;font-weight:700;">{{ $plan['price'] ?? '' }}<span style="font-size:0.875rem;font-weight:400;color:#6b7280;">{{ $plan['period'] ?? '' }}</span></div>
            <ul style="list-style:none;padding:0;margin:1rem 0;color:#4b5563;">
                @foreach(($plan['features'] ?? []) as $feature)
                    <li style="padding:0.25rem 0;">{{ $feature }}</li>
                @endforeach
            </ul>
            <a href="{{ $plan['ctaUrl'] ?? '#' }}" style="display:inline-block;background:#3b82f6;color:#fff;padding:0.5rem 1.5rem;border-radius:0.375rem;text-decoration:none;font-weight:500;">{{ $plan['ctaText'] ?? 'Choose' }}</a>
        </div>
    @endforeach
</div>
