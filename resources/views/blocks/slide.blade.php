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
    @php
        // WebP <picture> for the background: a full-size PNG/JPG hero is the
        // usual LCP killer, so prefer the pre-generated WebP variants when the
        // asset resolved during enrichment. Blade can't glue a directive to a
        // word ("400w@if"), so the srcset is assembled in PHP.
        $bgVariants = $data['_bg_variants'] ?? [];
        $bgWebpSrcset = implode(', ', array_filter([
            !empty($bgVariants['webp_400']) ? $bgVariants['webp_400'] . ' 400w' : null,
            !empty($bgVariants['webp_800']) ? $bgVariants['webp_800'] . ' 800w' : null,
            !empty($bgVariants['webp_1600']) ? $bgVariants['webp_1600'] . ' 1600w' : null,
        ]));
        // Non-WebP fallback stays a raster variant (or the original) so the
        // <img> in <picture> is a valid last resort.
        $bgFallback = $bgVariants['large_1600'] ?? $bgVariants['medium_800'] ?? $bgUrl;
        $bgW = $data['_bg_width'] ?? null;
        $bgH = $data['_bg_height'] ?? null;
    @endphp
    <div class="sp-bg{{ $kenburns ? ' sp-kenburns' : '' }}">
      @if($bgWebpSrcset !== '')
        <picture>
          <source srcset="{{ $bgWebpSrcset }}" sizes="100vw" type="image/webp">
          <img src="{{ $bgFallback }}" alt="" decoding="async"
               @if($bgW) width="{{ $bgW }}" @endif @if($bgH) height="{{ $bgH }}" @endif
               @if($eager) fetchpriority="high" @else loading="lazy" @endif>
        </picture>
      @else
        <img src="{{ $bgUrl }}" alt="" decoding="async"
             @if($bgW) width="{{ $bgW }}" @endif @if($bgH) height="{{ $bgH }}" @endif
             @if($eager) fetchpriority="high" @else loading="lazy" @endif>
      @endif
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
  {{-- Content stage: positioning context for the layers. Defaults to the full
       slide box (inset:0) so existing sliders are unchanged; sites can restyle
       .sp-stage (e.g. to a centered container) to box the content. --}}
  <div class="sp-stage">{!! $children !!}</div>
</div>
