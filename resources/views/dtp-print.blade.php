<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ e($issue['title'] ?? 'Magazine') }}</title>
@if(!empty($fontsUrl))
<link rel="stylesheet" href="{{ $fontsUrl }}">
@endif
<style>
/* Print-only view: one .page per PDF page, sized exactly to the document.
   @page in px keeps the same unit space the editor + web viewer use, so the
   flow engine's baked placements stay WYSIWYG. */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
@page { size: {{ $pageW }}px {{ $pageH }}px; margin: 0; }
html, body { background: #fff; }
.page {
    position: relative;
    width: {{ $pageW }}px;
    height: {{ $pageH }}px;
    overflow: hidden;
    page-break-after: always;
    break-after: page;
}
.page:last-child { page-break-after: auto; break-after: auto; }
.page-frame { position: absolute; overflow: hidden; }
.page-frame img { display: block; max-width: 100%; }
</style>
</head>
<body>
@foreach($pages as $page)
<div class="page" style="{{ $page['style'] }}">
    @foreach($page['frames'] as $frame)
    <div class="page-frame" style="{{ $frame['style'] }}">{!! $frame['html'] !!}</div>
    @endforeach
</div>
@endforeach
</body>
</html>
