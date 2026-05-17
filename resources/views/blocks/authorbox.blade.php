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
<div class="authorbox-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $showAvatar = $data['showAvatar'] ?? true;
    $showBio = $data['showBio'] ?? true;
    $showSocialLinks = $data['showSocialLinks'] ?? false;
    $layout = $data['layout'] ?? 'horizontal';
    $isVertical = $layout === 'vertical';
    // $author would be populated at build time
    $author = $author ?? [];
@endphp
<div style="border:1px solid var(--color-border,#e2e8f0);border-radius:0.75rem;padding:1.5rem;{{ $isVertical ? 'text-align:center;' : 'display:flex;align-items:flex-start;gap:1rem;' }}">
    @if($showAvatar)
        @if(!empty($author['avatar']))
            <img src="{{ $author['avatar'] }}" alt="" style="width:{{ $isVertical ? '64px' : '56px' }};height:{{ $isVertical ? '64px' : '56px' }};border-radius:50%;object-fit:cover;{{ $isVertical ? 'margin:0 auto 0.5rem;' : '' }}" />
        @else
            <div style="width:{{ $isVertical ? '64px' : '56px' }};height:{{ $isVertical ? '64px' : '56px' }};border-radius:50%;background:#e5e7eb;{{ $isVertical ? 'margin:0 auto 0.5rem;' : '' }}"></div>
        @endif
    @endif
    <div>
        <div style="font-weight:600;">{{ $author['name'] ?? 'Author' }}</div>
        @if($showBio)
            <p style="color:#6b7280;font-size:0.875rem;margin-top:0.25rem;">{{ $author['bio'] ?? '' }}</p>
        @endif
        @if($showSocialLinks && !empty($author['social']))
            <div style="display:flex;gap:0.75rem;margin-top:0.5rem;{{ $isVertical ? 'justify-content:center;' : '' }}">
                @foreach($author['social'] as $platform => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" style="color:#3b82f6;font-size:0.875rem;text-decoration:none;">{{ ucfirst($platform) }}</a>
                @endforeach
            </div>
        @endif
    </div>
</div>

</div>