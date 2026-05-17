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
<div class="sharebuttons-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $platforms = $data['platforms'] ?? ['twitter', 'facebook', 'linkedin', 'email', 'copy'];
    $style = $data['style'] ?? 'icons';
    $showLabels = $data['showLabels'] ?? false;
    $isButtons = $style === 'buttons';
    $isMinimal = $style === 'minimal';
    // Current page URL for sharing
    $shareUrl = $shareUrl ?? url()->current();
    $shareTitle = $shareTitle ?? '';
    $shareLinks = [
        'twitter' => 'https://twitter.com/intent/tweet?url=' . urlencode($shareUrl) . '&text=' . urlencode($shareTitle),
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($shareUrl),
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($shareUrl),
        'email' => 'mailto:?subject=' . rawurlencode($shareTitle) . '&body=' . rawurlencode($shareUrl),
    ];
    $platformNames = ['twitter' => 'Twitter', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'email' => 'Email', 'copy' => 'Copy Link'];
    $btnStyle = $isButtons
        ? 'display:inline-flex;align-items:center;gap:4px;padding:0.375rem 0.75rem;background:#f3f4f6;border-radius:var(--border-radius-sm,0.375rem);border:1px solid var(--color-border,#e2e8f0);color:inherit;text-decoration:none;font-size:0.875rem;'
        : ($isMinimal
            ? 'color:#6b7280;text-decoration:none;font-size:0.875rem;'
            : 'display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#f3f4f6;border-radius:50%;color:inherit;text-decoration:none;font-size:0.75rem;');
@endphp
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
    @foreach($platforms as $platform)
        @if($platform === 'copy')
            <button type="button" onclick="navigator.clipboard.writeText('{{ $shareUrl }}')" style="{{ $btnStyle }}cursor:pointer;border:{{ $isButtons ? '1px solid var(--color-border,#e2e8f0)' : 'none' }};background:{{ ($isMinimal) ? 'none' : '#f3f4f6' }};">
                @if($showLabels) {{ $platformNames[$platform] ?? $platform }} @else {{ substr($platformNames[$platform] ?? $platform, 0, 2) }} @endif
            </button>
        @else
            <a href="{{ $shareLinks[$platform] ?? '#' }}" target="_blank" rel="noopener noreferrer" style="{{ $btnStyle }}">
                @if($showLabels) {{ $platformNames[$platform] ?? $platform }} @else {{ substr($platformNames[$platform] ?? $platform, 0, 2) }} @endif
            </a>
        @endif
    @endforeach
</div>

</div>