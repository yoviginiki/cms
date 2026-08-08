@php use App\Domain\Publishing\Services\LocalePaths; @endphp
<section class="post-related" aria-label="Свързани публикации">
    <h2 class="post-related__title">Прочети още</h2>
    <div class="post-related__grid">
        @foreach($posts as $post)
            <a class="post-related__card" href="{{ LocalePaths::urlPath($site, $post) }}">
                @if($post->featured_image)
                    <span class="post-related__media"><img src="{{ $post->featured_image }}" alt="" loading="lazy"></span>
                @endif
                <span class="post-related__t">{{ $post->title }}</span>
            </a>
        @endforeach
    </div>
</section>
