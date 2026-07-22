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
    $eyebrow = $d['eyebrow'] ?? 'Practise now';
    $title = $d['title'] ?? 'Zen meditation timer';
    $presets = (is_array($d['presets'] ?? null) && $d['presets']) ? array_map('intval', $d['presets']) : [5, 10, 15, 20, 30, 45];
    $defaultMinutes = (int) ($d['defaultMinutes'] ?? ($presets[0] ?? 5));
    $showJourneys = array_key_exists('showJourneys', $d) ? (bool) $d['showJourneys'] : true;
    $journeys = (is_array($d['journeys'] ?? null) && $d['journeys']) ? $d['journeys'] : [
        '3-day opening' => [5, 10, 15],
        '5-day steady' => [5, 8, 12, 15, 20],
        '5-day deepening' => [10, 15, 20, 25, 30],
    ];
    $storeKey = $d['storeKey'] ?? 'rr-med';
    $runtimeConfig = ['storeKey' => $storeKey, 'journeys' => $journeys];
    $mm = str_pad((string) $defaultMinutes, 2, '0', STR_PAD_LEFT);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="meditation-timer-block rr-app-tool rr-meditation-tool {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
    <script type="application/json" class="rr-config">@json($runtimeConfig)</script>
    @if($eyebrow || $title)
    <div class="tool-heading"><div>
        @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        @if($title)<h2>{{ $title }}</h2>@endif
    </div></div>
    @endif
    <div class="meditation-timer-panel">
        <div class="zen-ring" style="--timer-progress:0deg"><strong>{{ $mm }}:00</strong><span>ready</span></div>
        <div class="time-presets">
            @foreach($presets as $m)
                <button type="button" class="{{ (int) $m === $defaultMinutes ? 'selected' : '' }}">{{ (int) $m }}</button>
            @endforeach
        </div>
        <div class="tool-actions">
            <button type="button" class="button button-ink">Begin with bell</button>
            <button type="button" class="button button-quiet">Reset</button>
        </div>
    </div>
    @if($showJourneys && count($journeys))
    <div class="journey-panel">
        <select aria-label="Choose a journey">
            @foreach(array_keys($journeys) as $name)
                <option value="{{ $name }}">{{ $name }}</option>
            @endforeach
        </select>
        <ul class="journey-days"></ul>
    </div>
    @endif
</div>
