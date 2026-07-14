@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba, $data ?? []);
    $__customClass = BlockStyle::buildClasses($__adv, $__ba);
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);

    $value = null;
    $label = trim((string) ($data['label'] ?? ''));
    if (!empty($data['queryId'])) {
        try {
            $__query = \App\Models\SavedQuery::find($data['queryId']);
            if ($__query) {
                $__result = app(\App\Domain\Collections\Queries\QueryRunner::class)->run($__query);
                $value = match ($__result['type']) {
                    'value' => $__result['value'],
                    'records' => $__result['total'],
                    'table' => $__result['total'],
                };
                $label = $label !== '' ? $label : ($__result['label'] ?? $__query->name);
            }
        } catch (\Throwable $e) {
            logger()->warning("query-stat render failed ({$data['queryId']}): {$e->getMessage()}");
        }
    }

    $sizeMap = ['sm' => '1.6rem', 'md' => '2.4rem', 'lg' => '3.2rem', 'xl' => '4.4rem'];
    $fontSize = $sizeMap[$data['size'] ?? 'lg'] ?? '3.2rem';
    $textAlign = in_array($data['textAlign'] ?? '', ['left','center','right']) ? $data['textAlign'] : 'center';
    $display = $value !== null
        ? (is_numeric($value) && floor((float) $value) != $value ? number_format((float) $value, 2) : number_format((float) $value))
        : null;
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="query-stat-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}text-align:{{ $textAlign }};" @if($__htmlId) id="{{ $__htmlId }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@if($display === null)
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">Pick a saved query for this stat.</p>
@else
    <div class="query-stat-value" style="font-size:{{ $fontSize }};font-weight:700;line-height:1.1;">{{ $data['prefix'] ?? '' }}{{ $display }}{{ $data['suffix'] ?? '' }}</div>
    @if($label)<div class="query-stat-label" style="font-size:.9rem;color:var(--color-text-muted,#6b6864);margin-top:.3rem;">{{ $label }}</div>@endif
@endif
</div>
