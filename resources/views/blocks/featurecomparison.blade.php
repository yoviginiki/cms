@use('App\Support\Blocks\BlockStyle')
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
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="featurecomparison-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $plans = $data['plans'] ?? [];
    $features = $data['features'] ?? [];
@endphp
<div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
        <thead>
            <tr>
                <th style="text-align:left;padding:0.75rem;border-bottom:2px solid var(--color-border,#e2e8f0);color:#6b7280;font-weight:500;">Feature</th>
                @foreach($plans as $plan)
                    <th style="text-align:center;padding:0.75rem;border-bottom:2px solid var(--color-border,#e2e8f0);">
                        <div style="font-weight:600;color:#1f2937;">{{ $plan['name'] ?? '' }}</div>
                        <div style="font-size:0.75rem;color:#6b7280;">{{ $plan['price'] ?? '' }}</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($features as $fi => $feat)
                <tr style="{{ $fi % 2 === 0 ? 'background:var(--color-bg-alt,#f8fafc);' : '' }}">
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

</div>