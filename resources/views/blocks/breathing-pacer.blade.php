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
    $eyebrow = $d['eyebrow'] ?? 'Interactive practice';
    $title = $d['title'] ?? 'Breathing pacer';
    $soundLabel = $d['soundLabel'] ?? 'Gentle cues';
    $soundDefault = array_key_exists('soundDefault', $d) ? (bool) $d['soundDefault'] : true;
    $advancedAt = (int) ($d['advancedAt'] ?? 20);
    $roundOptions = (is_array($d['roundOptions'] ?? null) && $d['roundOptions']) ? $d['roundOptions'] : [3, 5, 8];
    $defaultRounds = (int) ($d['defaultRounds'] ?? 5);
    $phases = (is_array($d['phases'] ?? null) && $d['phases']) ? $d['phases'] : [
        ['label' => 'Inhale', 'value' => 3, 'min' => 3, 'max' => 60, 'step' => 1, 'locked' => false],
        ['label' => 'Hold gently', 'value' => 3, 'min' => 3, 'max' => 60, 'step' => 1, 'locked' => true],
        ['label' => 'Exhale', 'value' => 3, 'min' => 3, 'max' => 60, 'step' => 1, 'locked' => true],
        ['label' => 'Rest empty', 'value' => 3, 'min' => 3, 'max' => 60, 'step' => 1, 'locked' => true],
    ];
    $first = $phases[0] ?? ['label' => 'Inhale', 'value' => 3];
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="breathing-pacer-block rr-app-tool rr-breath-tool {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" data-advanced-at="{{ $advancedAt }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
    <div class="tool-heading">
        <div>
            @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
            <h2>{{ $title }}</h2>
        </div>
        <label class="sound-toggle"><input type="checkbox" @if($soundDefault) checked @endif> {{ $soundLabel }}</label>
    </div>
    <div class="pacer-stage" aria-live="polite">
        <div class="breath-orb orb-in" style="--phase-time:{{ (float) ($first['value'] ?? 3) }}s">
            <div class="orb-progress" style="--progress:0deg"></div>
            <div class="orb-copy"><strong>{{ $first['label'] ?? 'Inhale' }}</strong><span>{{ (int) ($first['value'] ?? 3) }}</span></div>
        </div>
        <div class="round-readout"><span>Round 1 / {{ $defaultRounds }}</span><span>about 1 min</span></div>
    </div>
    <div class="phase-settings">
        @foreach($phases as $p)
            @php $locked = !empty($p['locked']); @endphp
            <label>
                <span>{{ $p['label'] ?? 'Breathe' }}</span>
                <div class="stepper">
                    <button type="button" @if($locked) disabled @endif aria-label="Decrease {{ $p['label'] ?? 'phase' }}">&minus;</button>
                    <output>{{ (float) ($p['value'] ?? 3) }}s</output>
                    <button type="button" @if($locked) disabled @endif aria-label="Increase {{ $p['label'] ?? 'phase' }}">+</button>
                </div>
                <input type="range" min="{{ (float) ($p['min'] ?? 3) }}" max="{{ (float) ($p['max'] ?? 60) }}" step="{{ (float) ($p['step'] ?? 1) }}" @if($locked) disabled @endif aria-label="{{ $p['label'] ?? 'phase' }} duration" value="{{ (float) ($p['value'] ?? 3) }}">
            </label>
        @endforeach
    </div>
    <div class="round-settings">
        <span>Rounds</span>
        @foreach($roundOptions as $r)
            <button type="button" class="{{ (int) $r === $defaultRounds ? 'selected' : '' }}">{{ (int) $r }}</button>
        @endforeach
    </div>
    <div class="tool-actions">
        <button type="button" class="button button-ink">Start practice</button>
        <button type="button" class="button button-quiet">Reset</button>
    </div>
</div>
