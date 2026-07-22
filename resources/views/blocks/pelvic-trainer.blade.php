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
    $rounds = (int) ($d['rounds'] ?? 6);
    $eyebrow = $d['eyebrow'] ?? ('Guided coordination · ' . $rounds . ' rounds');
    $phases = (is_array($d['phases'] ?? null) && $d['phases']) ? $d['phases'] : [
        ['label' => 'Arrive', 'cue' => 'Feel the weight of the pelvis. Do nothing yet.', 'seconds' => 8],
        ['label' => 'Inhale & widen', 'cue' => 'Let the lower ribs, belly and pelvic floor receive the breath.', 'seconds' => 5],
        ['label' => 'Gentle lift', 'cue' => 'Lift at about 30% effort — no glute or abdominal squeeze.', 'seconds' => 3],
        ['label' => 'Release fully', 'cue' => 'Let go for longer than you lifted. Notice the difference.', 'seconds' => 6],
    ];
    $first = $phases[0] ?? ['label' => 'Arrive', 'cue' => '', 'seconds' => 8];
    $runtimeConfig = ['rounds' => $rounds, 'phases' => $phases];
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="pelvic-trainer-block rr-app-tool rr-pelvic-tool {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
    <script type="application/json" class="rr-config">@json($runtimeConfig)</script>
    <div class="pelvic-visual" data-phase="0"><span></span><span></span><span></span></div>
    <div class="pelvic-copy">
        @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h2>{{ $first['label'] ?? 'Arrive' }}</h2>
        <p>{{ $first['cue'] ?? '' }}</p>
        <strong>{{ (int) ($first['seconds'] ?? 8) }}s <small>· round 1/{{ $rounds }}</small></strong>
        <div class="tool-actions">
            <button type="button" class="button button-ink">Begin</button>
            <button type="button" class="button button-quiet">Reset</button>
        </div>
    </div>
</div>
