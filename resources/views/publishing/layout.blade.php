<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {!! $headContent !!}
    @if(!empty($customCss))
        <style>{!! $customCss !!}</style>
    @endif
    {!! $headScripts ?? '' !!}
</head>
<body>
    <main>
        {!! $renderedBlocks !!}
    </main>
    {!! $bodyScripts ?? '' !!}
</body>
</html>
