@use('App\Support\Blocks\BlockStyle')
@use('App\Support\Blocks\BlockEffects')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba, $data ?? []);
    $__customClass = BlockStyle::buildClasses($__adv, $__ba);
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
    $__imageFilter = BlockEffects::imageFilterStyle($data ?? []);

    $int = fn ($k, $def, $min, $max) => max($min, min($max, (int) ($data[$k] ?? $def)));
    $enum = fn ($k, $def, $allowed) => in_array($data[$k] ?? $def, $allowed, true) ? ($data[$k] ?? $def) : $def;
    $bool = fn ($k, $def) => ($data[$k] ?? $def) !== false && ($data[$k] ?? $def) !== 0 && ($data[$k] ?? $def) !== '0';
    $safeColor = fn ($v, $default) => (is_string($v) && $v !== '' && preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s.,%]+\)|hsla?\([\d\s.,%]+\)|var\(--[\w-]+\)|transparent|[a-zA-Z]+)$/', $v)) ? $v : $default;
    $shadows = ['none', 'soft', 'medium', 'strong'];
    $shadowCss = [
        'none' => '0 0 0 0 transparent',
        'soft' => '0 6px 18px -6px rgba(0,0,0,.35)',
        'medium' => '0 14px 32px -10px rgba(0,0,0,.45)',
        'strong' => '0 24px 48px -12px rgba(0,0,0,.55)',
    ];

    // ── composition ──
    $height = $int('height', 360, 80, 1600);
    $mobileHeight = $int('mobileHeight', 220, 60, 1000);
    $stripHeight = $int('stripHeight', 0, 0, 2000);              // 0 = auto
    $variation = $enum('sizeVariation', 'strong', ['none', 'subtle', 'strong']);
    $overlap = $int('overlap', 22, 0, 60);
    $scatter = $int('scatter', 24, 0, 60);
    $align = $enum('align', 'start', ['start', 'center']);
    $full = $enum('width', 'contained', ['contained', 'full']) === 'full';
    $offsetStart = $int('offsetStart', 24, 0, 600);
    $offsetEnd = $int('offsetEnd', 24, 0, 600);
    $padding = $int('padding', 40, 0, 300);
    $radius = $int('radius', 3, 0, 60);
    // ── look ──
    $blend = $enum('blend', 'multiply', ['multiply', 'screen', 'darken', 'luminosity', 'normal']);
    $opacity = $int('opacity', 92, 30, 100) / 100;
    $background = $safeColor($data['background'] ?? null, 'transparent');
    $borderColor = $safeColor($data['borderColor'] ?? null, 'transparent');
    $borderWidth = $int('borderWidth', 0, 0, 12);
    $shadow = $enum('shadow', 'none', $shadows);
    // ── hover ──
    $hoverBorderColor = $safeColor($data['hoverBorderColor'] ?? null, '#ffffff');
    $hoverBorderWidth = $int('hoverBorderWidth', 2, 0, 12);
    $hoverGlow = $bool('hoverGlow', true);
    $hs = $data['hoverShadow'] ?? 'strong';
    $hoverShadow = is_bool($hs) ? ($hs ? 'strong' : 'none') : (in_array($hs, $shadows, true) ? $hs : 'strong');
    $hoverLift = $bool('hoverLift', true);
    // ── controls ──
    $arrows = $bool('arrows', true);
    $arrowsShow = $enum('arrowsShow', 'hover', ['hover', 'always']);
    $arrowsPosition = $enum('arrowsPosition', 'sides', ['sides', 'bottom-right', 'bottom-center', 'top-right']);
    $arrowsSize = $enum('arrowsSize', 'md', ['sm', 'md', 'lg']);
    $arrowsColor = $safeColor($data['arrowsColor'] ?? null, 'var(--color-text,#111)');
    $arrowsBg = $safeColor($data['arrowsBg'] ?? null, 'color-mix(in srgb,var(--color-bg,#fff) 82%,transparent)');
    $arrowsMobile = $bool('arrowsMobile', false);
    $drag = $bool('drag', true);
    $scrollbar = $bool('scrollbar', false);
    $autoplay = $int('autoplay', 0, 0, 200);
    $lightbox = $bool('lightbox', true);
    $openOn = $enum('openOn', 'click', ['click', 'dblclick']);
    $arrowPx = ['sm' => 36, 'md' => 44, 'lg' => 56][$arrowsSize];

    // Deterministic "random" composition: scale + vertical offset + z-order per index.
    $scaleSeq = match ($variation) {
        'none' => [1.0],
        'subtle' => [1.0, 0.88, 0.94, 0.82, 1.0, 0.9, 0.86],
        default => [1.0, 0.72, 0.9, 0.58, 0.96, 0.66, 0.84, 0.52, 0.92, 0.7],
    };
    $dySeq = [0, 0.55, -0.45, 0.85, -0.2, 0.35, -0.7, 0.5, -0.35, 0.25];
    $zSeq = [3, 1, 4, 2, 5, 1, 3, 2, 4, 1];

    $items = [];
    foreach (($data['images'] ?? []) as $img) {
        $src = is_array($img) ? ($img['src'] ?? $img['url'] ?? '') : (string) $img;
        if ($src === '') continue;
        $n = count($items);
        $items[] = [
            'src' => $src,
            'alt' => is_array($img) ? (string) ($img['alt'] ?? '') : '',
            'caption' => is_array($img) ? trim((string) ($img['caption'] ?? '')) : '',
            'link' => is_array($img) ? trim((string) ($img['link'] ?? '')) : '',
            'w' => is_array($img) ? ($img['width'] ?? null) : null,
            'h' => is_array($img) ? ($img['height'] ?? null) : null,
            'scale' => $scaleSeq[$n % count($scaleSeq)],
            'dy' => $dySeq[$n % count($dySeq)] * ($scatter / 100),
            'z' => $zSeq[$n % count($zSeq)],
        ];
    }
    $vars = implode('', [
        "--lg-dh:{$height}px;--lg-mh:{$mobileHeight}px;--lg-strip:" . ($stripHeight > 0 ? "{$stripHeight}px" : 'auto') . ';',
        "--lg-overlap:" . ($overlap / 100) . ";--lg-opacity:{$opacity};--lg-blend:{$blend};--lg-bg:{$background};",
        "--lg-pad:{$padding}px;--lg-off-s:{$offsetStart}px;--lg-off-e:{$offsetEnd}px;--lg-radius:{$radius}px;",
        "--lg-border:{$borderColor};--lg-bw:{$borderWidth}px;--lg-shadow:{$shadowCss[$shadow]};",
        "--lg-hborder:{$hoverBorderColor};--lg-hbw:{$hoverBorderWidth}px;--lg-hshadow:{$shadowCss[$hoverShadow]};",
        "--lg-hglow:" . ($hoverGlow ? "0 0 22px 2px color-mix(in srgb,{$hoverBorderColor} 60%,transparent)" : '0 0 0 0 transparent') . ';',
        "--lg-arrow:{$arrowPx}px;--lg-arrow-c:{$arrowsColor};--lg-arrow-bg:{$arrowsBg};",
    ]);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
@if($lightbox && $items)@include('blocks.partials.lightbox')@endif
@once('linear-gallery-css')
<style>
.lg-block{position:relative;width:100%;max-width:100%;min-width:0;contain:inline-size;--lg-h:var(--lg-dh)}
.lg-block--full{width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw)}
.lg-track{display:flex;align-items:center;width:100%;height:var(--lg-strip,auto);overflow-x:auto;overflow-y:clip;scrollbar-width:none;-ms-overflow-style:none;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;overscroll-behavior-y:auto;padding:calc(var(--lg-pad) + var(--lg-h) * .35) var(--lg-off-e) calc(var(--lg-pad) + var(--lg-h) * .35) var(--lg-off-s);box-sizing:border-box;min-height:calc(var(--lg-h) + 2 * var(--lg-pad));background:var(--lg-bg);isolation:isolate;-webkit-user-select:none;user-select:none;touch-action:pan-x pan-y;outline:none}
.lg-track::-webkit-scrollbar{display:none}
.lg-block[data-scrollbar="1"] .lg-track{scrollbar-width:thin;-ms-overflow-style:auto}
.lg-block[data-scrollbar="1"] .lg-track::-webkit-scrollbar{display:block;height:6px}
.lg-block[data-scrollbar="1"] .lg-track::-webkit-scrollbar-thumb{background:color-mix(in srgb,var(--color-text,#111) 30%,transparent);border-radius:3px}
.lg-block[data-drag="1"] .lg-track,.lg-block[data-drag="1"] .lg-item>a,.lg-block[data-drag="1"] .lg-item>span{cursor:grab}
.lg-track.is-dragging,.lg-track.is-dragging .lg-item>a{cursor:grabbing!important}
.lg-track.is-dragging .lg-item{pointer-events:none}
.lg-track--center{justify-content:safe center}
.lg-track::after{content:'';flex:0 0 var(--lg-off-e)}
.lg-item{position:relative;flex:0 0 auto;margin:0;height:calc(var(--lg-h) * var(--s,1));transform:translateY(calc(var(--lg-h) * var(--dy,0)));margin-left:calc(var(--lg-h) * var(--lg-overlap) * -1);mix-blend-mode:var(--lg-blend);opacity:var(--lg-opacity);z-index:var(--z,1);transition:transform .35s cubic-bezier(.2,.7,.2,1),opacity .3s ease;will-change:transform}
.lg-item:first-child{margin-left:0}
.lg-item>a,.lg-item>span{display:block;position:relative;height:100%;border-radius:var(--lg-radius);box-shadow:0 0 0 var(--lg-bw) var(--lg-border),var(--lg-shadow);transition:box-shadow .3s ease;cursor:zoom-in}
.lg-item>span{cursor:default}
.lg-item img{display:block;height:100%;width:auto;max-width:none;object-fit:cover;border-radius:var(--lg-radius);-webkit-user-drag:none;user-drag:none;pointer-events:none}
.lg-item:hover,.lg-item:focus-within{z-index:60;mix-blend-mode:normal;opacity:1}
.lg-block[data-lift="1"] .lg-item:hover,.lg-block[data-lift="1"] .lg-item:focus-within{transform:translateY(calc(var(--lg-h) * var(--dy,0) - 8px)) scale(1.03)}
.lg-item:hover>a,.lg-item:focus-within>a,.lg-item:hover>span{box-shadow:0 0 0 var(--lg-hbw) var(--lg-hborder),var(--lg-hglow),var(--lg-hshadow)}
.lg-item>a:focus-visible{outline:none}
.lg-cap{position:absolute;left:0;right:0;bottom:0;padding:.5em .7em;font-size:.75rem;line-height:1.3;color:#fff;background:linear-gradient(to top,rgba(0,0,0,.65),transparent);border-radius:0 0 var(--lg-radius) var(--lg-radius);opacity:0;transform:translateY(4px);transition:opacity .3s ease,transform .3s ease;pointer-events:none}
.lg-item:hover .lg-cap,.lg-item:focus-within .lg-cap{opacity:1;transform:none}
.lg-arrow{position:absolute;z-index:70;width:var(--lg-arrow);height:var(--lg-arrow);border-radius:999px;border:1px solid color-mix(in srgb,var(--lg-arrow-c) 25%,transparent);background:var(--lg-arrow-bg);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:var(--lg-arrow-c);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;transition:opacity .25s ease,transform .2s ease,background .25s ease}
.lg-arrow svg{width:45%;height:45%;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.lg-arrow:hover{transform:scale(1.06)}
.lg-arrow[disabled]{opacity:.25!important;cursor:default;transform:none}
.lg-block[data-arrows="hover"] .lg-arrow{opacity:0}
.lg-block[data-arrows="hover"]:hover .lg-arrow,.lg-block[data-arrows="hover"] .lg-arrow:focus-visible{opacity:1}
.lg-block[data-arrows-pos="sides"] .lg-arrow{top:50%;transform:translateY(-50%)}
.lg-block[data-arrows-pos="sides"] .lg-arrow:hover{transform:translateY(-50%) scale(1.06)}
.lg-block[data-arrows-pos="sides"] .lg-prev{left:12px}
.lg-block[data-arrows-pos="sides"] .lg-next{right:12px}
.lg-block[data-arrows-pos="bottom-right"] .lg-arrow{bottom:12px}
.lg-block[data-arrows-pos="bottom-right"] .lg-prev{right:calc(var(--lg-arrow) + 20px)}
.lg-block[data-arrows-pos="bottom-right"] .lg-next{right:12px}
.lg-block[data-arrows-pos="bottom-center"] .lg-arrow{bottom:12px}
.lg-block[data-arrows-pos="bottom-center"] .lg-prev{left:calc(50% - var(--lg-arrow) - 6px)}
.lg-block[data-arrows-pos="bottom-center"] .lg-next{left:calc(50% + 6px)}
.lg-block[data-arrows-pos="top-right"] .lg-arrow{top:12px}
.lg-block[data-arrows-pos="top-right"] .lg-prev{right:calc(var(--lg-arrow) + 20px)}
.lg-block[data-arrows-pos="top-right"] .lg-next{right:12px}
.lg-block--full .lg-arrow.lg-prev{left:max(12px,calc(50vw - 600px))}
.lg-block--full[data-arrows-pos="sides"] .lg-next{right:max(12px,calc(50vw - 600px))}
@media (hover:none){.lg-block[data-arrows-mobile="0"] .lg-arrow{display:none}.lg-block[data-arrows="hover"] .lg-arrow{opacity:1}.lg-item:active{z-index:60;mix-blend-mode:normal;opacity:1}}
@media (max-width:640px){.lg-block{--lg-h:var(--lg-mh)}.lg-track{padding:calc(var(--lg-pad) * .5 + var(--lg-h) * .3) 16px;min-height:calc(var(--lg-h) + var(--lg-pad));scroll-snap-type:x proximity;scroll-padding:16px}.lg-track::after{flex-basis:16px}.lg-item{scroll-snap-align:center;margin-left:calc(var(--lg-h) * var(--lg-overlap) * -.6)}.lg-item:first-child{margin-left:0}.lg-cap{opacity:1;transform:none;font-size:.7rem}.lg-block{--lg-arrow:36px}}
@media (prefers-reduced-motion:reduce){.lg-item,.lg-item>a,.lg-cap,.lg-arrow{transition:none}.lg-block[data-lift="1"] .lg-item:hover{transform:translateY(calc(var(--lg-h) * var(--dy,0)))}}
</style>
<script>
(function(){
  function init(block){
    if(block.__lg)return;block.__lg=true;
    var track=block.querySelector('.lg-track'),prev=block.querySelector('.lg-prev'),next=block.querySelector('.lg-next');
    if(!track)return;
    var canDrag=block.getAttribute('data-drag')!=='0';
    var drag=null,moved=0,raf=0,vel=0,lastX=0,lastT=0,autoplay=parseFloat(block.getAttribute('data-autoplay')||'0'),paused=false;
    function stopInertia(){if(raf){cancelAnimationFrame(raf);raf=0;}}
    function updateArrows(){
      if(!prev||!next)return;
      var max=track.scrollWidth-track.clientWidth-1;
      prev.disabled=track.scrollLeft<=1;next.disabled=track.scrollLeft>=max;
    }
    if(canDrag){
      track.addEventListener('pointerdown',function(e){
        if(e.pointerType==='touch'||e.button!==0)return;
        stopInertia();drag={x:e.clientX,left:track.scrollLeft,id:e.pointerId};moved=0;vel=0;lastX=e.clientX;lastT=performance.now();
      });
      track.addEventListener('pointermove',function(e){
        if(!drag)return;
        var dx=e.clientX-drag.x;
        if(Math.abs(dx)>4&&!track.classList.contains('is-dragging')){track.classList.add('is-dragging');block.classList.add('is-touched');try{track.setPointerCapture(drag.id);}catch(err){}}
        moved=Math.max(moved,Math.abs(dx));
        track.scrollLeft=drag.left-dx;
        var t=performance.now();if(t-lastT>0){vel=(e.clientX-lastX)/(t-lastT);}lastX=e.clientX;lastT=t;
      });
      var endDrag=function(){
        if(!drag)return;drag=null;
        var wasDrag=track.classList.contains('is-dragging');
        setTimeout(function(){track.classList.remove('is-dragging');},0);
        if(wasDrag&&Math.abs(vel)>0.05){
          var v=-vel*16;
          (function step(){v*=0.94;if(Math.abs(v)<0.4){raf=0;updateArrows();return;}track.scrollLeft+=v;raf=requestAnimationFrame(step);})();
        }
      };
      track.addEventListener('pointerup',endDrag);track.addEventListener('pointercancel',endDrag);track.addEventListener('lostpointercapture',endDrag);
      track.addEventListener('click',function(e){if(moved>6){e.preventDefault();e.stopPropagation();moved=0;}},true);
    }
    track.addEventListener('touchstart',function(){block.classList.add('is-touched');},{passive:true});
    // overflow-y is clipped, but focus() can still nudge scrollTop programmatically — keep the strip level
    track.addEventListener('scroll',function(){if(track.scrollTop)track.scrollTop=0;if(!raf)updateArrows();},{passive:true});
    var page=function(dir){stopInertia();track.scrollBy({left:dir*Math.max(200,track.clientWidth*0.6),behavior:'smooth'});block.classList.add('is-touched');};
    if(prev)prev.addEventListener('click',function(){page(-1);});
    if(next)next.addEventListener('click',function(){page(1);});
    track.addEventListener('keydown',function(e){if(e.key==='ArrowRight'){e.preventDefault();page(1);}else if(e.key==='ArrowLeft'){e.preventDefault();page(-1);}});
    if(autoplay>0&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches){
      var last=0;
      block.addEventListener('pointerenter',function(){paused=true;});block.addEventListener('pointerleave',function(){paused=false;});
      track.addEventListener('touchstart',function(){paused=true;},{passive:true});
      (function tick(t){
        if(!last)last=t;var dt=Math.min(64,t-last);last=t;
        if(!paused&&!drag&&!raf&&!document.hidden){
          var max=track.scrollWidth-track.clientWidth;
          if(track.scrollLeft>=max-1){track.scrollLeft=0;}else{track.scrollLeft+=autoplay*dt/1000;}
        }
        requestAnimationFrame(tick);
      })(0);
    }
    if(track.classList.contains('lg-track--center')&&track.scrollWidth>track.clientWidth){track.scrollLeft=(track.scrollWidth-track.clientWidth)/2;}
    updateArrows();
    window.addEventListener('resize',updateArrows);
    if(window.ResizeObserver){new ResizeObserver(updateArrows).observe(track);}
  }
  function boot(){document.querySelectorAll('.lg-block').forEach(init);}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
</script>
@endonce
<div class="lg-block {{ $full ? 'lg-block--full' : '' }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" data-lightbox="{{ $lightbox ? '1' : '0' }}" data-open="{{ $openOn }}" data-lift="{{ $hoverLift ? '1' : '0' }}" data-drag="{{ $drag ? '1' : '0' }}" data-scrollbar="{{ $scrollbar ? '1' : '0' }}" data-arrows="{{ $arrowsShow }}" data-arrows-pos="{{ $arrowsPosition }}" data-arrows-mobile="{{ $arrowsMobile ? '1' : '0' }}" data-autoplay="{{ $autoplay }}"
     style="position:relative;{{ $vars }}{{ $__sharedStyle }}"
     @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif aria-label="{{ $__adv['ariaLabel'] ?? 'Image gallery' }}" role="region">
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@if($arrows && count($items) > 1)
    <button type="button" class="lg-arrow lg-prev" aria-label="Scroll left"><svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg></button>
    <button type="button" class="lg-arrow lg-next" aria-label="Scroll right"><svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg></button>
@endif
<div class="lg-track {{ $align === 'center' ? 'lg-track--center' : '' }}" tabindex="0" aria-roledescription="carousel">
    @foreach($items as $i => $it)
        @php
            $href = $it['link'] !== '' ? $it['link'] : $it['src'];
            $isLightbox = $lightbox && $it['link'] === '';
            $isExternal = $it['link'] !== '' && preg_match('#^https?://#', $it['link']);
            $label = $it['caption'] !== '' ? $it['caption'] : ($it['alt'] !== '' ? $it['alt'] : 'Image ' . ($i + 1));
            $imgTag = '<img class="img-filtered" src="' . e($it['src']) . '" alt="' . e($it['alt']) . '"' . ($it['w'] ? ' width="' . (int) $it['w'] . '"' : '') . ($it['h'] ? ' height="' . (int) $it['h'] . '"' : '') . ' loading="' . ($i < 4 ? 'eager' : 'lazy') . '" decoding="async" draggable="false" style="' . $__imageFilter . '">';
            $capTag = $it['caption'] !== '' ? '<figcaption class="lg-cap">' . e($it['caption']) . '</figcaption>' : '';
        @endphp
        <figure class="lg-item" style="--s:{{ $it['scale'] }};--dy:{{ $it['dy'] }};--z:{{ $it['z'] }};">
            @if($lightbox || $it['link'] !== '')
            <a href="{{ e($href) }}" @if($isLightbox) data-glb-item data-caption="{{ e($it['caption']) }}" @endif @if($isExternal) target="_blank" rel="noopener" @endif aria-label="{{ e($label) }}">{!! $imgTag !!}{!! $capTag !!}</a>
            @else
            <span>{!! $imgTag !!}{!! $capTag !!}</span>
            @endif
        </figure>
    @endforeach
</div>
</div>
