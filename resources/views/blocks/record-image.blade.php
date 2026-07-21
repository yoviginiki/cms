@use('App\Support\Blocks\BlockStyle')
@use('App\Support\Blocks\RecordDisplay')
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

    $record = $__record ?? null;
    $collection = $__collection ?? null;
    $fieldKey = $data['field'] ?? '';
    if (!$fieldKey && $collection) { $fieldKey = RecordDisplay::firstImageField($collection); }
    $rawValue = ($record && $fieldKey) ? ($record->data[$fieldKey] ?? null) : null;
    // gallery fields hold uuid[]; >1 image renders the carousel + lightbox (v3)
    $assetIds = is_array($rawValue) ? array_values(array_filter($rawValue, 'is_string')) : (is_string($rawValue) ? [$rawValue] : []);
    $alt = $record?->title ?? '';

    $srcFor = fn ($id, $variant = null) => $site ? RecordDisplay::assetUrl($site, $id, $variant) : null;
    $srcsetFor = fn ($id) => implode(', ', array_filter([
        ($u = $srcFor($id, 'small_400')) ? "{$u} 400w" : null,
        ($u = $srcFor($id, 'medium_800')) ? "{$u} 800w" : null,
    ]));

    $ratioMap = ['16:9' => '56.25%', '4:3' => '75%', '1:1' => '100%', '3:2' => '66.67%'];
    $ratio = $ratioMap[$data['aspectRatio'] ?? ''] ?? null;
    $fit = in_array($data['objectFit'] ?? 'cover', ['cover','contain']) ? ($data['objectFit'] ?? 'cover') : 'cover';
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="record-image-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@if(count($assetIds) === 1)
    @php $src = $srcFor($assetIds[0]); $srcset = $srcsetFor($assetIds[0]); @endphp
    @if($ratio)
        <div style="position:relative;width:100%;padding-top:{{ $ratio }};overflow:hidden;">
            <img src="{{ $src }}" @if($srcset) srcset="{{ $srcset }}" sizes="(max-width: 640px) 100vw, 800px" @endif alt="{{ $alt }}" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:{{ $fit }};">
        </div>
    @else
        <img src="{{ $src }}" @if($srcset) srcset="{{ $srcset }}" sizes="(max-width: 640px) 100vw, 800px" @endif alt="{{ $alt }}" loading="lazy" style="max-width:100%;height:auto;display:block;">
    @endif
@elseif(count($assetIds) > 1)
    @once
    <style>
        .rg-carousel{position:relative}
        .rg-main{position:relative;width:100%;padding-top:75%;overflow:hidden;background:var(--color-surface,#f4f2ef)}
        .rg-main img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;cursor:zoom-in}
        .rg-main img.rg-on{display:block}
        .rg-nav{position:absolute;top:50%;transform:translateY(-50%);border:0;background:rgba(0,0,0,.45);color:#fff;width:2.4rem;height:2.4rem;font-size:1.2rem;cursor:pointer;line-height:1}
        .rg-nav:hover{background:rgba(0,0,0,.65)}
        .rg-prev{left:.5rem}.rg-next{right:.5rem}
        .rg-thumbs{display:flex;gap:.4rem;margin-top:.5rem;flex-wrap:wrap}
        .rg-thumbs button{border:2px solid transparent;padding:0;background:none;cursor:pointer;line-height:0}
        .rg-thumbs button.rg-on{border-color:var(--color-primary,#333)}
        .rg-thumbs img{width:64px;height:64px;object-fit:cover;display:block}
        .rg-lightbox{position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;align-items:center;justify-content:center;z-index:9999;cursor:zoom-out}
        .rg-lightbox.rg-on{display:flex}
        .rg-lightbox img{max-width:94vw;max-height:92vh;object-fit:contain}
    </style>
    <script>
    document.addEventListener('click', function (e) {
        var c = e.target.closest('.rg-carousel');
        var lb = e.target.closest('.rg-lightbox');
        if (lb) { lb.classList.remove('rg-on'); return; }
        if (!c) return;
        var imgs = c.querySelectorAll('.rg-main img');
        var thumbs = c.querySelectorAll('.rg-thumbs button');
        var idx = parseInt(c.getAttribute('data-rg-idx') || '0', 10);
        var show = function (n) {
            n = (n + imgs.length) % imgs.length;
            imgs.forEach(function (im, i) { im.classList.toggle('rg-on', i === n); });
            thumbs.forEach(function (t, i) { t.classList.toggle('rg-on', i === n); });
            c.setAttribute('data-rg-idx', n);
        };
        if (e.target.closest('.rg-prev')) { show(idx - 1); }
        else if (e.target.closest('.rg-next')) { show(idx + 1); }
        else if (e.target.closest('.rg-thumbs button')) {
            show(Array.prototype.indexOf.call(thumbs, e.target.closest('button')));
        } else if (e.target.closest('.rg-main img')) {
            var box = c.querySelector('.rg-lightbox');
            box.querySelector('img').src = e.target.closest('img').getAttribute('data-full') || e.target.closest('img').src;
            box.classList.add('rg-on');
        }
    });
    </script>
    @endonce
    <div class="rg-carousel" data-rg-idx="0">
        <div class="rg-main">
            @foreach($assetIds as $i => $id)
                <img src="{{ $srcFor($id, 'medium_800') ?? $srcFor($id) }}" data-full="{{ $srcFor($id) }}"
                     alt="{{ $alt }}{{ $i ? ' — ' . ($i + 1) : '' }}" @if($i) loading="lazy" @endif class="{{ $i === 0 ? 'rg-on' : '' }}">
            @endforeach
            <button type="button" class="rg-nav rg-prev" aria-label="Previous image">‹</button>
            <button type="button" class="rg-nav rg-next" aria-label="Next image">›</button>
        </div>
        <div class="rg-thumbs">
            @foreach($assetIds as $i => $id)
                <button type="button" class="{{ $i === 0 ? 'rg-on' : '' }}" aria-label="Image {{ $i + 1 }}">
                    <img src="{{ $srcFor($id, 'thumb_200') ?? $srcFor($id) }}" alt="" loading="lazy">
                </button>
            @endforeach
        </div>
        <div class="rg-lightbox" role="dialog" aria-label="{{ $alt }}"><img src="" alt="{{ $alt }}"></div>
    </div>
@endif
</div>
