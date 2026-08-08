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
@php
    $rowScopeClass = 'row-' . substr(md5(($__htmlId ?: '') . ($data['layout'] ?? '') . spl_object_id((object)$data)), 0, 8);

    // P5: custom mobile stack order — a permutation of 0-based column indices.
    // Columns collapse to one column below 768px; `order` re-sequences them.
    $mobileOrderCss = '';
    $stackOrder = $data['stack_order'] ?? null;
    if (is_array($stackOrder) && count($stackOrder) >= 2) {
        $n = count($stackOrder);
        $seen = [];
        $valid = true;
        foreach ($stackOrder as $v) {
            $v = (int) $v;
            if ($v < 0 || $v >= $n || isset($seen[$v])) { $valid = false; break; }
            $seen[$v] = true;
        }
        if ($valid && count($seen) === $n) {
            $posByOrig = array_flip(array_map('intval', array_values($stackOrder))); // origIndex => displayPos
            for ($i = 0; $i < $n; $i++) {
                $mobileOrderCss .= ".{$rowScopeClass} > div > *:nth-child(" . ($i + 1) . "){order:{$posByOrig[$i]};}";
            }
        }
    }
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<style>@media(max-width:767px){.{{ $rowScopeClass }} > div{grid-template-columns:1fr !important;}{{ $mobileOrderCss }}}</style>
<div class="row-block {{ $rowScopeClass }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $layout = $data['layout'] ?? '1/2+1/2';

    // Column widths as 12-grid spans, from the layout preset...
    $presetSpans = [
        '1' => [12], '1/1' => [12], '1/2+1/2' => [6, 6], '1/3+2/3' => [4, 8],
        '2/3+1/3' => [8, 4], '1/3+1/3+1/3' => [4, 4, 4],
        '1/4+1/4+1/4+1/4' => [3, 3, 3, 3], '1/4+3/4' => [3, 9], '3/4+1/4' => [9, 3],
    ];
    $spans = $presetSpans[$layout] ?? [6, 6];

    // ...unless explicit 12-grid col_spans are provided.
    $colSpans = $data['col_spans'] ?? null;
    if (is_array($colSpans) && count($colSpans) >= 2) {
        $validSpans = [];
        foreach ($colSpans as $s) {
            $n = (int) $s;
            if ($n >= 1 && $n <= 12) $validSpans[] = $n;
        }
        if (count($validSpans) === count($colSpans)) $spans = $validSpans;
    }

    // The number of grid TRACKS follows the row's ACTUAL column count — not the
    // layout preset — so a row that gained/lost a column renders the same here
    // (preview/published) as in the editor, which is column-count driven. The
    // preset/col_spans only decide the relative widths; if they disagree with
    // the real column count, fall back to an even split.
    $colCount = (isset($childrenArray) && is_array($childrenArray)) ? count($childrenArray) : count($spans);
    $colCount = max(1, $colCount);
    if (count($spans) !== $colCount) {
        $base = max(1, intdiv(12, $colCount));
        $spans = array_fill(0, $colCount, $base);
    }
    $gridCols = implode(' ', array_map(fn ($n) => $n . 'fr', $spans));

    // Auto-collapse: multi-column but fewer than 2 columns actually have
    // content → render a single full-width track.
    if ($colCount > 1 && isset($childrenArray) && is_array($childrenArray)) {
        $populatedCols = 0;
        foreach ($childrenArray as $colHtml) {
            $stripped = trim(strip_tags(preg_replace('/<!--.*?-->/s', '', (string)$colHtml)));
            $hasElements = preg_match('/<(img|video|iframe|svg|table|ul|ol|blockquote|hr|audio|figure|h[1-6])\b/i', (string)$colHtml);
            $hasBlocks = preg_match('/class="[^"]*-block\b/i', (string)$colHtml);
            if ($stripped !== '' || $hasElements || $hasBlocks) $populatedCols++;
        }
        if ($populatedCols < 2) $gridCols = '1fr';
    }

    $gap = BlockStyle::safeDim($data['gap'] ?? '16px') ?: '16px';
    $maxW = BlockStyle::safeDim($data['max_width'] ?? '');
    $validAligns = ['start', 'center', 'end', 'stretch'];
    $rawVAlign = $data['vertical_align'] ?? 'stretch';
    $vAlign = in_array($rawVAlign, $validAligns) ? $rawVAlign : 'stretch';

    $style = "display:grid;grid-template-columns:{$gridCols};gap:{$gap};align-items:{$vAlign};";
    if ($maxW) {
        $style .= "max-width:{$maxW};margin:0 auto;";
    }
@endphp
<div style="{{ $style }}">
    {!! $children !!}
</div>

</div>
