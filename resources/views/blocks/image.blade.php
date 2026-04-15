<figure class="image-block">
    @if(!empty($data['url']) || !empty($data['asset_id']))
        <img src="{{ $data['url'] ?? '' }}" alt="{{ $data['alt'] ?? '' }}" loading="lazy"
            @if(($data['size'] ?? 'full') !== 'full') style="max-width: {{ ['small' => '320px', 'medium' => '640px', 'large' => '960px'][$data['size'] ?? 'full'] ?? '100%' }};" @endif
        >
    @endif
    @if(!empty($data['caption']))
        <figcaption>{{ $data['caption'] }}</figcaption>
    @endif
</figure>
