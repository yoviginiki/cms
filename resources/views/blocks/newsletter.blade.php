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
<div class="newsletter-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $heading = $data['heading'] ?? 'Subscribe';
    $description = $data['description'] ?? '';
    $buttonText = $data['buttonText'] ?? 'Subscribe';
    $endpoint = $data['endpoint'] ?? '';
    $style = $data['style'] ?? 'inline';
    $isCard = $style === 'card';
    $isFull = $style === 'full-width';
    $tsShadowPresets = ['sm' => '0 1px 2px rgba(0,0,0,0.15)', 'md' => '0 2px 4px rgba(0,0,0,0.25)', 'lg' => '0 4px 8px rgba(0,0,0,0.4)', 'outline' => '-1px -1px 0 rgba(0,0,0,0.3),1px -1px 0 rgba(0,0,0,0.3),-1px 1px 0 rgba(0,0,0,0.3),1px 1px 0 rgba(0,0,0,0.3)', 'glow' => '0 0 10px rgba(255,255,255,0.8),0 0 20px rgba(255,255,255,0.4)'];
    $headingTextShadow = $tsShadowPresets[$data['headingTextShadow'] ?? ''] ?? '';
@endphp
<div style="{{ $isCard ? 'border:1px solid var(--color-border,#e2e8f0);border-radius:0.75rem;padding:2rem;text-align:center;' : '' }}{{ $isFull ? 'background:#eff6ff;padding:2rem;text-align:center;border-radius:var(--border-radius-md,0.5rem);' : '' }}">
    <h3 style="font-weight:600;margin-bottom:0.25rem;{{ $headingTextShadow ? "text-shadow:{$headingTextShadow};" : '' }}">{{ $heading }}</h3>
    @if($description)
        <p style="color:#6b7280;font-size:0.875rem;margin-bottom:1rem;">{{ $description }}</p>
    @endif
    <form action="{{ $endpoint }}" method="POST" style="display:flex;gap:0.5rem;{{ ($isCard || $isFull) ? 'justify-content:center;' : '' }}max-width:400px;{{ ($isCard || $isFull) ? 'margin:0 auto;' : '' }}">
        <input type="email" placeholder="Email" required style="flex:1;padding:0.5rem 0.75rem;border:1px solid #d1d5db;border-radius:var(--border-radius-sm,0.375rem);font-size:0.875rem;" />
        <button type="submit" style="background:#3b82f6;color:var(--color-text-inverse,#fff);padding:0.5rem 1.25rem;border:none;border-radius:var(--border-radius-sm,0.375rem);font-weight:500;cursor:pointer;">{{ $buttonText }}</button>
    </form>
</div>

</div>