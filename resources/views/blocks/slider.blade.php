@php
    use App\Support\Blocks\SliderRender;
    /** Root of a slider entity. $data['_config'] is built by BuildPageService
        enrichment (SliderRender::buildConfig). Children = rendered slides. */
    $config = $data['_config'] ?? null;
    $slideCount = $config ? count($config['slides'] ?? []) : count($childrenArray ?? []);
    $sw = $config['swiper'] ?? [];
    $h = $config['height'] ?? [];
    $heightVars = '--sp-h-desktop:' . SliderRender::safeDim($h['desktop'] ?? null, '70vh')
        . ';--sp-h-tablet:' . SliderRender::safeDim($h['tablet'] ?? null, '60vh')
        . ';--sp-h-mobile:' . SliderRender::safeDim($h['mobile'] ?? null, '80vh');
@endphp
@if($config && $slideCount > 0)
{{-- fixed height via CSS vars reserves space: no CLS. no-js -> js swap is
     inline so a runtime-load failure can never blank the first slide --}}
<section class="sp-slider no-js"
         id="slider-{{ $config['id'] }}"
         data-slider-id="{{ $config['id'] }}"
         style="{{ $heightVars }}"
         aria-roledescription="carousel"
         aria-label="Slider"
         tabindex="0">
  <script>document.currentScript.parentElement.classList.replace('no-js','js');</script>
  <script type="application/json" data-slider-config>@json($config)</script>
  <div class="swiper">
    <div class="swiper-wrapper">{!! $children !!}</div>
  </div>
  <div class="sp-ui">
    @if(!empty($sw['autoplay']))
      <div class="sp-progress" aria-hidden="true"><b data-slider-progress></b></div>
      <button class="sp-pause" type="button" data-slider-pause aria-pressed="false" aria-label="Pause autoplay">Pause</button>
    @endif
    @if(!empty($sw['navigation']))
      <button class="sp-arrow -prev" type="button" data-slider-prev aria-label="Previous slide">&larr;</button>
      <button class="sp-arrow -next" type="button" data-slider-next aria-label="Next slide">&rarr;</button>
    @endif
    @if(!empty($sw['pagination']))
      <div class="sp-bullets" role="tablist" aria-label="Choose slide">
        @for($i = 0; $i < $slideCount; $i++)
          <button class="sp-bullet" type="button" role="tab" data-slider-bullet
                  aria-label="Go to slide {{ $i + 1 }}" @if($i === 0) aria-current="true" @endif></button>
        @endfor
      </div>
    @endif
    <div class="sp-counter" aria-live="polite"><span data-slider-counter-current>1</span> / {{ $slideCount }}</div>
  </div>
</section>
@else
<!-- slider: no published slides -->
@endif
