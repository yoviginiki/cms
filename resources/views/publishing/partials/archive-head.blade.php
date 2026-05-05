    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if(!empty($rssUrl))
        <link rel="alternate" type="application/rss+xml" title="{{ $site->name }} Feed" href="{{ $rssUrl }}">
    @endif
    @if(!empty($designTokensCss))<style>{!! $designTokensCss !!}</style>@endif
    @if(!empty($criticalCss))<style>{!! $criticalCss !!}</style>@endif
    @if(!empty($customCss))<style>{!! $customCss !!}</style>@endif
