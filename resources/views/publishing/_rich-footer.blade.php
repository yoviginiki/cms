@php
    /**
     * Rich multi-column site footer (opt-in via settings.rich_footer = true).
     * Global: any site can enable it. Columns come from settings.footer_columns
     * (array of category slugs); each column lists the category's latest posts.
     * Styling is intentionally minimal/inline here so it works on any site;
     * a site's custom_css (e.g. footer[role=contentinfo]{background:#141414})
     * themes it. Falls back gracefully when categories/posts are missing.
     */
    $fs = $site->settings ?? [];
    $colSlugs = $fs['footer_columns'] ?? [];
    $perCol = (int) ($fs['footer_column_posts'] ?? 4);
    $cols = [];
    foreach ($colSlugs as $slug) {
        $cat = \App\Models\Category::where('site_id', $site->id)->where('slug', $slug)->first();
        if (!$cat) { continue; }
        // Include descendant-category posts so a parent (e.g. Изкуствата) is not
        // empty when its content lives in child categories.
        $catIds = [$cat->id];
        $childIds = \App\Models\Category::where('site_id', $site->id)->where('parent_id', $cat->id)->pluck('id')->all();
        $catIds = array_merge($catIds, $childIds);
        $posts = \App\Models\Post::where('site_id', $site->id)->whereIn('category_id', $catIds)
            ->where('status', 'published')->with('category')
            ->orderByDesc('published_at')->limit($perCol)->get(['id', 'title', 'slug', 'category_id', 'featured_image']);
        $cols[] = ['cat' => $cat, 'posts' => $posts];
    }
@endphp
<div class="site-footer">
    @if(!empty($cols))
    <div class="site-footer__cols">
        @foreach($cols as $col)
        <div class="site-footer__col">
            <h2 class="site-footer__h"><a href="{{ $col['cat']->url_path }}/">{{ $col['cat']->name }}</a></h2>
            <ul class="site-footer__list">
                @foreach($col['posts'] as $p)
                <li>
                    @if($p->featured_image)<a href="{{ $p->url_path }}" class="site-footer__thumb" aria-hidden="true" tabindex="-1"><img src="{{ $p->featured_image }}" alt="" loading="lazy"></a>@endif
                    <a href="{{ $p->url_path }}" class="site-footer__link">{{ $p->title }}</a>
                </li>
                @endforeach
            </ul>
        </div>
        @endforeach
    </div>
    @endif

    @if(!empty($fs['footer_tagline']))
    <div class="site-footer__tagline"
         @if(!empty($fs['footer_tagline_bg'])) style="background-image:linear-gradient(rgba(10,10,10,.72),rgba(10,10,10,.72)),url('{{ $fs['footer_tagline_bg'] }}');background-size:cover;background-position:center;" @endif>
        <p>{!! nl2br(e($fs['footer_tagline'])) !!}</p>
    </div>
    @endif

    <div class="site-footer__legal">
        <p>{!! $fs['footer_copyright'] ?? ('© ' . e($site->name)) !!}</p>
    </div>
</div>
