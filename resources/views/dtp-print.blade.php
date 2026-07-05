<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ e($issue['title'] ?? 'Magazine') }}</title>
@if(!empty($fontsUrl))
<link rel="stylesheet" href="{{ $fontsUrl }}">
@endif
@php
    $marks = !empty($withMarks);
    // print bleed: symmetric, from the document's page bleed (default 9pt ≈ 3mm)
    $bleed = $marks ? max(0, (int) ($bleedSize ?? 9)) : 0;
    $markLen = 12;   // crop mark length
    $markGap = 3;    // gap between trim edge and mark start
    // sheet slop must hold BOTH the art bleed and the crop marks
    $slop = $marks ? max($bleed, $markLen + $markGap + 2) : 0;
    $sheetW = $pageW + 2 * $slop;
    $sheetH = $pageH + 2 * $slop;
@endphp
<style>
/* Print-only view: one .sheet per PDF page. Without marks the sheet IS the
   trim box; with marks the sheet grows by the bleed on every side, content
   shifts in by the bleed, edge-anchored art overflows into it, and crop
   marks show the trim. @page in px keeps the editor's unit space (WYSIWYG). */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
@page { size: {{ $sheetW }}px {{ $sheetH }}px; margin: 0; }
html, body { background: #fff; }
.sheet {
    position: relative;
    width: {{ $sheetW }}px;
    height: {{ $sheetH }}px;
    overflow: hidden;
    page-break-after: always;
    break-after: page;
}
.sheet:last-child { page-break-after: auto; break-after: auto; }
.page {
    position: absolute;
    top: {{ $slop }}px; left: {{ $slop }}px;
    width: {{ $pageW }}px;
    height: {{ $pageH }}px;
    overflow: {{ $marks ? 'visible' : 'hidden' }};
}
.page-frame { position: absolute; overflow: hidden; }
.page-frame img { display: block; max-width: 100%; }
@if($marks)
/* bleed-box clip: art may overflow the trim but never past the bleed */
.sheet { clip-path: inset(0); }
.crop { position: absolute; background: #000; z-index: 99; }
.crop.h { height: 0.75px; width: {{ $markLen }}px; }
.crop.v { width: 0.75px; height: {{ $markLen }}px; }
@endif
</style>
</head>
<body>
@foreach($pages as $page)
<div class="sheet">
    <div class="page" style="{{ $page['style'] }}">
        @foreach($page['frames'] as $frame)
        <div class="page-frame" style="{{ $frame['style'] }}">{!! $frame['html'] !!}</div>
        @endforeach
    </div>
    @if($marks)
        {{-- 8 crop marks: 2 per trim corner, kept OUT of the bleed art area --}}
        @php $l = $slop; $r = $slop + $pageW; $t = $slop; $b = $slop + $pageH; @endphp
        <div class="crop h" style="top:{{ $t }}px; left:{{ $l - $markGap - $markLen }}px;"></div>
        <div class="crop v" style="left:{{ $l }}px; top:{{ $t - $markGap - $markLen }}px;"></div>
        <div class="crop h" style="top:{{ $t }}px; left:{{ $r + $markGap }}px;"></div>
        <div class="crop v" style="left:{{ $r }}px; top:{{ $t - $markGap - $markLen }}px;"></div>
        <div class="crop h" style="top:{{ $b }}px; left:{{ $l - $markGap - $markLen }}px;"></div>
        <div class="crop v" style="left:{{ $l }}px; top:{{ $b + $markGap }}px;"></div>
        <div class="crop h" style="top:{{ $b }}px; left:{{ $r + $markGap }}px;"></div>
        <div class="crop v" style="left:{{ $r }}px; top:{{ $b + $markGap }}px;"></div>
    @endif
</div>
@endforeach
</body>
</html>
