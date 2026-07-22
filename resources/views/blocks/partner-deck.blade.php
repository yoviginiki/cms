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

    $d = $data ?? [];
    $eyebrow = $d['eyebrow'] ?? 'Invitation, never obligation';
    $buttonLabel = $d['buttonLabel'] ?? 'Draw another';
    $cards = (is_array($d['cards'] ?? null) && $d['cards']) ? $d['cards'] : [
        ['title' => 'Three-minute arrival', 'body' => 'Sit facing each other. Share one easy breathing rhythm for three minutes. No fixing, no performance, no finish.'],
        ['title' => 'The touch map', 'body' => 'Each person shows three kinds of touch: yes, maybe and not today. Switch roles. Curiosity matters more than agreement.'],
        ['title' => 'Pause is part of the dance', 'body' => 'Choose a neutral pause word. When it is heard: stop, take three easy exhales, then choose together how to continue.'],
    ];
    $first = $cards[0] ?? ['title' => '', 'body' => ''];
    $runtimeConfig = ['cards' => array_values($cards)];
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="partner-deck-block rr-app-tool rr-partner-deck {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
    <script type="application/json" class="rr-config">@json($runtimeConfig)</script>
    <div class="deck-number">01</div>
    @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
    <h2>{{ $first['title'] ?? '' }}</h2>
    <p>{{ $first['body'] ?? '' }}</p>
    <div class="tool-actions">
        <button type="button" class="button button-ink">{{ $buttonLabel }}</button>
        <span>1 / {{ count($cards) }}</span>
    </div>
</div>
