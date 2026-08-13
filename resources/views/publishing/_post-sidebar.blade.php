@php use App\Domain\Publishing\Services\LocalePaths; @endphp
<aside class="post-aside" aria-label="Дясна колона">
    <a class="post-aside__banner" href="{{ $bannerLink }}" target="_blank" rel="noopener nofollow">
        <img src="{{ $bannerUrl }}" alt="Creative Europe" loading="lazy">
    </a>
    @if($latest->count())
        <div class="post-aside__box">
            <h3 class="post-aside__h">ПОСЛЕДНО</h3>
            <ul class="post-aside__list">
                @foreach($latest as $lp)
                    <li><a href="{{ LocalePaths::urlPath($site, $lp) }}">
                        @if($lp->featured_image)
                            <span class="post-aside__thumb"><img src="{{ $lp->featured_image }}" alt="" loading="lazy"></span>
                        @endif
                        <span class="post-aside__t">{{ $lp->title }}</span>
                    </a></li>
                @endforeach
            </ul>
        </div>
    @endif
    @foreach($catGroups as $g)
        <div class="post-aside__box">
            <h3 class="post-aside__h"><a href="{{ $g['url'] }}">{{ $g['name'] }}</a></h3>
            <ul class="post-aside__list">
                @foreach($g['posts'] as $lp)
                    <li><a href="{{ LocalePaths::urlPath($site, $lp) }}">
                        @if($lp->featured_image)
                            <span class="post-aside__thumb"><img src="{{ $lp->featured_image }}" alt="" loading="lazy"></span>
                        @endif
                        <span class="post-aside__t">{{ $lp->title }}</span>
                    </a></li>
                @endforeach
            </ul>
        </div>
    @endforeach
</aside>
