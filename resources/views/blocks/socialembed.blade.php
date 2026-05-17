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
<div class="socialembed-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $url = $data['url'] ?? '';
    $platform = $data['platform'] ?? 'auto';

    if ($platform === 'auto' && $url) {
        if (str_contains($url, 'twitter.com') || str_contains($url, 'x.com')) {
            $platform = 'twitter';
        } elseif (str_contains($url, 'instagram.com')) {
            $platform = 'instagram';
        } elseif (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            $platform = 'youtube';
        } elseif (str_contains($url, 'tiktok.com')) {
            $platform = 'tiktok';
        }
    }

    $platformLabels = [
        'twitter' => 'Twitter / X',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'auto' => 'Social Media',
    ];
    $platformColors = [
        'twitter' => '#1da1f2',
        'instagram' => '#e1306c',
        'youtube' => '#ff0000',
        'tiktok' => '#000000',
        'auto' => '#6b7280',
    ];
    $label = $platformLabels[$platform] ?? 'Social Media';
    $color = $platformColors[$platform] ?? '#6b7280';
@endphp
@if($url)
    <div style="border:1px solid var(--color-border,#e2e8f0);border-radius:var(--border-radius-md,0.5rem);padding:1rem 1.25rem;display:flex;align-items:center;gap:0.75rem;">
        <div style="width:2.5rem;height:2.5rem;border-radius:var(--border-radius-sm,0.375rem);background:{{ $color }};display:flex;align-items:center;justify-content:center;color:var(--color-text-inverse,#fff);font-weight:700;font-size:0.875rem;flex-shrink:0;">
            {{ strtoupper(substr($label, 0, 1)) }}
        </div>
        <div style="min-width:0;flex:1;">
            <div style="font-size:0.75rem;font-weight:500;color:#6b7280;text-transform:uppercase;margin-bottom:0.125rem;">{{ $label }}</div>
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" style="font-size:0.875rem;color:#3b82f6;text-decoration:none;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $url }}</a>
        </div>
    </div>
@endif

</div>