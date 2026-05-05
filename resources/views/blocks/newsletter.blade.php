@php
    $heading = $data['heading'] ?? 'Subscribe';
    $description = $data['description'] ?? '';
    $buttonText = $data['buttonText'] ?? 'Subscribe';
    $endpoint = $data['endpoint'] ?? '';
    $style = $data['style'] ?? 'inline';
    $isCard = $style === 'card';
    $isFull = $style === 'full-width';
@endphp
<div style="{{ $isCard ? 'border:1px solid #e5e7eb;border-radius:0.75rem;padding:2rem;text-align:center;' : '' }}{{ $isFull ? 'background:#eff6ff;padding:2rem;text-align:center;border-radius:0.5rem;' : '' }}">
    <h3 style="font-weight:600;margin-bottom:0.25rem;">{{ $heading }}</h3>
    @if($description)
        <p style="color:#6b7280;font-size:0.875rem;margin-bottom:1rem;">{{ $description }}</p>
    @endif
    <form action="{{ $endpoint }}" method="POST" style="display:flex;gap:0.5rem;{{ ($isCard || $isFull) ? 'justify-content:center;' : '' }}max-width:400px;{{ ($isCard || $isFull) ? 'margin:0 auto;' : '' }}">
        <input type="email" placeholder="Email" required style="flex:1;padding:0.5rem 0.75rem;border:1px solid #d1d5db;border-radius:0.375rem;font-size:0.875rem;" />
        <button type="submit" style="background:#3b82f6;color:#fff;padding:0.5rem 1.25rem;border:none;border-radius:0.375rem;font-weight:500;cursor:pointer;">{{ $buttonText }}</button>
    </form>
</div>
