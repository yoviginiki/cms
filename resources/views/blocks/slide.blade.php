@php
    use App\Support\Blocks\BlockStyle;
    use App\Support\Blocks\SliderRender;
    /** One slide. Children (already wrapped as .sp-layer by BuildPageService)
        arrive rendered in $children. First slide loads media eagerly (LCP);
        later slides lazy. */
    $bg = $data['background'] ?? [];
    $bgType = in_array($bg['type'] ?? '', ['image', 'video', 'color']) ? $bg['type'] : 'color';
    $bgUrl = $data['_bg_url'] ?? null; // resolved by BuildPageService enrichment
    $bgColor = BlockStyle::safeColor($bg['color'] ?? '') ?: 'transparent';
    $overlay = $data['_overlay_css'] ?? null; // pattern-validated at save + re-checked in enrichment
    $slideIndex = (int) ($data['_slide_index'] ?? 0);
    $slideNumber = $slideIndex + 1;
    $slideTotal = (int) ($data['_slide_total'] ?? 1);
    $eager = $slideIndex === 0;
    $kenburns = !empty($bg['kenBurns']);
@endphp
<div class="swiper-slide sp-slide"
     data-slide-id="{{ $data['_block_id'] ?? '' }}"
     role="group" aria-roledescription="slide"
     aria-label="slide {{ $slideNumber }} of {{ $slideTotal }}">
  @if($bgType === 'image' && $bgUrl)
    <div class="sp-bg{{ $kenburns ? ' sp-kenburns' : '' }}">
      <img src="{{ $bgUrl }}" alt=""
           @if($eager) fetchpriority="high" @else loading="lazy" @endif>
    </div>
  @elseif($bgType === 'video' && $bgUrl)
    <div class="sp-bg">
      <video src="{{ $bgUrl }}" muted autoplay loop playsinline
             preload="{{ $eager ? 'auto' : 'none' }}" aria-hidden="true"></video>
    </div>
  @else
    <div class="sp-bg" style="background:{{ $bgColor }}"></div>
  @endif
  @if($overlay)
    <div class="sp-bg-overlay" style="background:{{ $overlay }}"></div>
  @endif
  {!! $children !!}
</div>
