@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba);
    $__customClass = BlockStyle::safeClass($__adv['customClass'] ?? '');
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="pricingcard-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $planName = $data['planName'] ?? 'Plan';
    $price = $data['price'] ?? '$0';
    $period = $data['period'] ?? 'month';
    $features = $data['features'] ?? [];
    $ctaText = $data['ctaText'] ?? 'Get started';
    $ctaUrl = $data['ctaUrl'] ?? '#';
    $highlighted = !empty($data['highlighted']);
    $badge = $data['badge'] ?? '';
@endphp
<div style="border:2px solid {{ $highlighted ? '#3b82f6' : '#e5e7eb' }};border-radius:0.75rem;padding:2rem;text-align:center;position:relative;{{ $highlighted ? 'box-shadow:0 10px 25px -5px rgba(59,130,246,0.2);' : '' }}">
    @if($badge)
        <span style="position:absolute;top:-0.75rem;left:50%;transform:translateX(-50%);background:#3b82f6;color:#fff;font-size:0.75rem;padding:0.125rem 0.75rem;border-radius:9999px;">{{ $badge }}</span>
    @endif
    <h3 style="font-size:1.25rem;font-weight:600;margin:0 0 0.25rem;">{{ $planName }}</h3>
    <div style="margin-bottom:1.5rem;">
        <span style="font-size:2.5rem;font-weight:700;">{{ $price }}</span>
        @if($period && $period !== 'one-time')
            <span style="font-size:0.875rem;color:#6b7280;">/{{ $period }}</span>
        @endif
    </div>
    <ul style="list-style:none;padding:0;margin:0 0 1.5rem;text-align:left;">
        @foreach($features as $feat)
            <li style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0;font-size:0.875rem;{{ empty($feat['included']) ? 'color:#9ca3af;text-decoration:line-through;' : 'color:#374151;' }}">
                @if(!empty($feat['included']))
                    <span style="color:#22c55e;font-weight:700;">&#10003;</span>
                @else
                    <span style="color:#d1d5db;font-weight:700;">&#10005;</span>
                @endif
                {{ $feat['text'] ?? '' }}
            </li>
        @endforeach
    </ul>
    <a href="{{ $ctaUrl }}" style="display:inline-block;padding:0.625rem 1.5rem;border-radius:0.375rem;font-size:0.875rem;font-weight:500;text-decoration:none;{{ $highlighted ? 'background:#3b82f6;color:#fff;' : 'background:#f3f4f6;color:#374151;border:1px solid #d1d5db;' }}">{{ $ctaText }}</a>
</div>

</div>