@php
    $crumbs = [['label' => 'Home', 'url' => '/']];
    $current = $page ?? null;

    if ($current instanceof \App\Models\Post) {
        $crumbs[] = ['label' => 'Blog', 'url' => '/blog'];
        if ($current->category) {
            $crumbs[] = ['label' => $current->category->name, 'url' => ''/' . $current->category->slug];
        }
        $crumbs[] = ['label' => $current->title, 'url' => null];
    } elseif ($current instanceof \App\Models\Page) {
        // Build parent chain
        $chain = [];
        $p = $current;
        while ($p) {
            $chain[] = $p;
            $p = $p->parent;
        }
        $chain = array_reverse($chain);
        foreach ($chain as $i => $pg) {
            if ($i === count($chain) - 1) {
                $crumbs[] = ['label' => $pg->title, 'url' => null];
            } else {
                $crumbs[] = ['label' => $pg->title, 'url' => '/' . $pg->slug];
            }
        }
    }
@endphp
<nav aria-label="Breadcrumb" style="padding:0.75rem 0;font-size:0.875rem;">
    <ol style="list-style:none;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:0.25rem;">
        @foreach($crumbs as $i => $crumb)
            <li style="display:flex;align-items:center;gap:0.25rem;">
                @if($i > 0)<span style="color:#9ca3af;" aria-hidden="true">/</span>@endif
                @if($crumb['url'])
                    <a href="{{ $crumb['url'] }}" style="color:#6b7280;text-decoration:none;">{{ $crumb['label'] }}</a>
                @else
                    <span style="color:#1f2937;" aria-current="page">{{ $crumb['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
