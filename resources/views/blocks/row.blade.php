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
    $layoutMap = [
        '1' => '1fr',
        '1/1' => '1fr',
        '1/2+1/2' => '1fr 1fr',
        '1/3+2/3' => '1fr 2fr',
        '2/3+1/3' => '2fr 1fr',
        '1/3+1/3+1/3' => '1fr 1fr 1fr',
        '1/4+1/4+1/4+1/4' => '1fr 1fr 1fr 1fr',
        '1/4+3/4' => '1fr 3fr',
        '3/4+1/4' => '3fr 1fr',
    ];
    $layout = $data['layout'] ?? '1/2+1/2';
    $gridCols = $layoutMap[$layout] ?? '1fr 1fr';

    // P5: explicit 12-grid column widths override the preset when valid.
    $colSpans = $data['col_spans'] ?? null;
    if (is_array($colSpans) && count($colSpans) >= 2) {
        $validSpans = [];
        foreach ($colSpans as $s) {
            $n = (int) $s;
            if ($n >= 1 && $n <= 12) $validSpans[] = $n;
        }
        if (count($validSpans) === count($colSpans)) {
            $gridCols = implode(' ', array_map(fn ($n) => $n . 'fr', $validSpans));
        }
    }

    // Auto-collapse: if multi-column layout but only 1 column has real content, use 1fr
    if ($gridCols !== '1fr' && isset($childrenArray) && is_array($childrenArray)) {
        $populatedCols = 0;
        foreach ($childrenArray as $colHtml) {
            $stripped = trim(strip_tags(preg_replace('/<!--.*?-->/s', '', (string)$colHtml)));
            $hasElements = preg_match('/<(img|video|iframe|svg|table|ul|ol|blockquote|hr|audio|figure|h[1-6])\b/i', (string)$colHtml);
            $hasBlocks = preg_match('/class="[^"]*-block\b/i', (string)$colHtml);
            if ($stripped !== '' || $hasElements || $hasBlocks) $populatedCols++;
        }
        if (count($childrenArray) > 0 && $populatedCols < 2) {
            $gridCols = '1fr';
        }
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
