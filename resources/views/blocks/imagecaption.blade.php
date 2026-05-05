<figure class="image-caption image-caption--{{ $data['captionPosition'] ?? 'below' }}">
  @if(!empty($data['src']))<img src="{{ e($data['src']) }}" alt="{{ e($data['alt'] ?? '') }}" loading="lazy">@endif
  @if(!empty($data['caption']))<figcaption>{{ e($data['caption']) }}</figcaption>@endif
</figure>
