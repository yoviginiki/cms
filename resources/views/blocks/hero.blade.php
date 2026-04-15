<section class="hero-section" style="background-image: url('{{ $data['background_image'] ?? '' }}'); background-size: cover; background-position: center;">
    <div class="hero-content" style="padding: 4rem 2rem; text-align: center;">
        <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem;">{{ $data['title'] ?? '' }}</h1>
        @if(!empty($data['subtitle']))
            <p style="font-size: 1.25rem; opacity: 0.9; margin-bottom: 2rem;">{{ $data['subtitle'] }}</p>
        @endif
        @if(!empty($data['cta_text']) && !empty($data['cta_url']))
            <a href="{{ $data['cta_url'] }}" style="display: inline-block; padding: 0.75rem 2rem; background: #3b82f6; color: #fff; text-decoration: none; border-radius: 0.5rem; font-weight: 600;">{{ $data['cta_text'] }}</a>
        @endif
    </div>
</section>
