@php
    $type = $page['type'] ?? 'editorial_body';
    $pd = $page['data'] ?? [];
    $tall = $page['tall'] ?? false;
    $revealEnabled = ($rev['enabled'] ?? true);
@endphp
@switch($type)
    @case('cover')
        <section class="sl-section {{ $tall ? 'sl-tall' : '' }}">
            <div style="text-align:center;">
                @if(!empty($pd['eyebrow']))<p class="sl-eyebrow {{ $revealEnabled ? 'sl-reveal' : '' }}">{{ $pd['eyebrow'] }}</p>@endif
                <h1 class="sl-masthead {{ $revealEnabled ? 'sl-reveal' : '' }}" style="margin:1rem 0;">{{ $pd['masthead'] ?? '' }}</h1>
                @if(!empty($pd['mastheadMeta']))<p class="sl-eyebrow {{ $revealEnabled ? 'sl-reveal' : '' }}">{{ $pd['mastheadMeta'] }}</p>@endif
                @if(!empty($pd['divider']))<p class="sl-divider {{ $revealEnabled ? 'sl-reveal' : '' }}" style="margin:2rem 0;">{{ $pd['divider'] }}</p>@endif
                @if(!empty($pd['subtitle']))<p class="sl-subtitle {{ $revealEnabled ? 'sl-reveal' : '' }}">{{ $pd['subtitle'] }}</p>@endif
                @if(!empty($pd['hook']))<p class="sl-hook {{ $revealEnabled ? 'sl-reveal' : '' }}">{{ $pd['hook'] }}</p>@endif
                @if(($pd['showScrollHint'] ?? false) && ($hint['enabled'] ?? true))
                <div class="sl-scroll-hint">{{ $hint['text'] ?? 'Scroll' }}</div>
                @endif
            </div>
        </section>
        @break

    @case('quote')
        <section class="sl-section {{ $tall ? 'sl-tall' : '' }}">
            <div style="text-align:center;">
                @foreach(($pd['lines'] ?? []) as $line)
                <p class="sl-quote-line {{ $revealEnabled ? 'sl-reveal' : '' }}">{!! sl_emphasize($line['text'] ?? '', $line['emphasis'] ?? []) !!}</p>
                @endforeach
                @if($pd['showMark'] ?? false)<p class="sl-mark {{ $revealEnabled ? 'sl-reveal' : '' }}">·</p>@endif
            </div>
        </section>
        @break

    @case('editorial_title')
        <section class="sl-section {{ $tall ? 'sl-tall' : '' }}">
            <div style="text-align:center;">
                @if(!empty($pd['chapterLabel']))<p class="sl-chapter-label {{ $revealEnabled ? 'sl-reveal' : '' }}">{{ $pd['chapterLabel'] }}</p>@endif
                <h2 class="sl-chapter-title {{ $revealEnabled ? 'sl-reveal' : '' }} {{ ($pd['showDotMark'] ?? false) ? 'sl-dot-mark' : '' }}">{{ $pd['chapterTitle'] ?? '' }}</h2>
            </div>
        </section>
        @break

    @case('editorial_body')
        <section class="sl-section {{ $tall ? 'sl-tall' : '' }}" style="{{ ($pd['centered'] ?? false) ? 'text-align:center;' : 'text-align:left;' }}">
            <div style="max-width:var(--sl-{{ ($pd['maxWidth'] ?? 'reading') === 'wide' ? 'max-wide' : 'max-reading' }});margin:0 auto;">
                @foreach(($pd['paragraphs'] ?? []) as $para)
                <p class="sl-body-text {{ ($para['isLead'] ?? false) ? 'sl-lead' : '' }} {{ $revealEnabled ? 'sl-reveal' : '' }}">{!! sl_emphasize($para['text'] ?? '', $para['emphasis'] ?? []) !!}</p>
                @endforeach
                @if($pd['showMark'] ?? false)<p class="sl-mark {{ $revealEnabled ? 'sl-reveal' : '' }}" style="margin-top:2rem;">·</p>@endif
            </div>
        </section>
        @break

    @case('pull_quote')
        <section class="sl-section {{ $tall ? 'sl-tall' : '' }}">
            <div class="{{ ($pd['showLines'] ?? true) ? 'sl-pull-lines' : '' }} {{ $revealEnabled ? 'sl-reveal' : '' }}" style="max-width:var(--sl-max-wide);margin:0 auto;">
                <p class="sl-pull-quote">{!! sl_emphasize($pd['text'] ?? '', $pd['emphasis'] ?? []) !!}</p>
            </div>
        </section>
        @break

    @case('closing')
        <section class="sl-section {{ $tall ? 'sl-tall' : '' }}">
            <div style="text-align:center;" class="{{ $revealEnabled ? 'sl-reveal' : '' }}">
                <p class="sl-closing-line">{!! sl_emphasize($pd['line'] ?? '', $pd['emphasis'] ?? []) !!}</p>
            </div>
        </section>
        @break

    @case('footer')
        <footer class="sl-footer-section">
            @if(!empty($pd['mark']))<p class="sl-divider {{ $revealEnabled ? 'sl-reveal' : '' }}">{{ $pd['mark'] }}</p>@endif
            @foreach(($pd['lines'] ?? []) as $line)
            <p class="sl-eyebrow {{ $revealEnabled ? 'sl-reveal' : '' }}" style="margin-top:0.5rem;">{!! sl_emphasize($line['text'] ?? '', $line['emphasis'] ?? []) !!}</p>
            @endforeach
            @if(!empty($pd['meta']))<p class="sl-footer-meta {{ $revealEnabled ? 'sl-reveal' : '' }}">{{ $pd['meta'] }}</p>@endif
        </footer>
        @break
@endswitch

@php
if (!function_exists('sl_emphasize')) {
    function sl_emphasize(string $text, array $phrases): string {
        $escaped = e($text);
        foreach ($phrases as $phrase) {
            $ep = e($phrase);
            $escaped = str_replace($ep, '<em class="sl-em">' . $ep . '</em>', $escaped);
        }
        return $escaped;
    }
}
@endphp
