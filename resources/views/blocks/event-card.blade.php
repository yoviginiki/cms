@php
    use App\Support\Blocks\BlockStyle;

    $title = trim((string) ($data['title'] ?? ''));
    $city = trim((string) ($data['city'] ?? ''));
    $venue = trim((string) ($data['venue'] ?? ''));
    $desc = trim((string) ($data['short_description'] ?? ''));
    $startRaw = (string) ($data['start_at'] ?? '');
    $endRaw = (string) ($data['end_at'] ?? '');
    $isFree = !empty($data['is_free']);
    $ticketUrl = BlockStyle::safeUrl($data['ticket_url'] ?? '');
    $officialUrl = BlockStyle::safeUrl($data['official_url'] ?? '');

    $fmt = function (string $v): ?string {
        if ($v === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($v)->format('j M Y, H:i');
        } catch (\Throwable $e) {
            return $v; // fall back to the raw string (escaped on output)
        }
    };
    $start = $fmt($startRaw);
    $end = $fmt($endRaw);
    $place = trim($venue . ($venue !== '' && $city !== '' ? ', ' : '') . $city);
@endphp
<article class="event-card">
    @if ($title !== '')
        <h3 class="event-card__title">{{ $title }}</h3>
    @endif

    @if ($start !== null || $place !== '' || $isFree)
        <div class="event-card__meta">
            @if ($start !== null)
                <time class="event-card__time" datetime="{{ $startRaw }}">{{ $start }}@if ($end !== null) – {{ $end }}@endif</time>
            @endif
            @if ($place !== '')
                <span class="event-card__place">{{ $place }}</span>
            @endif
            @if ($isFree)
                <span class="event-card__free">Free entry</span>
            @endif
        </div>
    @endif

    @if ($desc !== '')
        <p class="event-card__desc">{{ $desc }}</p>
    @endif

    @if ($ticketUrl !== '' || $officialUrl !== '')
        <div class="event-card__links">
            @if ($ticketUrl !== '')
                <a class="event-card__ticket" href="{{ $ticketUrl }}" rel="noopener nofollow">Tickets</a>
            @endif
            @if ($officialUrl !== '')
                <a class="event-card__official" href="{{ $officialUrl }}" rel="noopener nofollow">Details</a>
            @endif
        </div>
    @endif
</article>
