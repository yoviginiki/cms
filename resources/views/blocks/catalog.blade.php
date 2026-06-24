@use('App\Support\Blocks\BlockStyle')
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
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="catalog-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $items = $data['items'] ?? [];
    $labels = $data['headerLabels'] ?? ['no.', 'title', 'subtitle', ''];
    $openFirst = !empty($data['openFirst']);
    $imgHeight = preg_replace('/[^a-zA-Z0-9.%]/', '', $data['imageHeight'] ?? '280px') ?: '280px';
    $imgFilter = in_array($data['imageFilter'] ?? 'grayscale', ['none','grayscale','sepia']) ? ($data['imageFilter'] ?? 'grayscale') : 'grayscale';
    $hoverReveal = ($data['imageHoverReveal'] ?? true) !== false;
    $filterCss = match($imgFilter) { 'grayscale' => 'grayscale(100%)', 'sepia' => 'sepia(100%)', default => 'none' };
    $safeUrl = fn($v) => preg_match('/^(javascript|data|vbscript)\s*:/i', preg_replace('/[\x00-\x1f\x7f\s]/', '', (string) $v)) ? '' : (string) $v;
@endphp
<style>
.catalog-block summary::-webkit-details-marker{display:none}
.catalog-block summary:hover{opacity:0.7}
.catalog-block details[open] summary .catalog-toggle{font-size:0;visibility:hidden;position:relative}
.catalog-block details[open] summary .catalog-toggle::after{content:'close';visibility:visible;font-size:0.65rem;position:absolute;right:0}
.catalog-block details:not([open]) summary .catalog-toggle{font-size:0;visibility:hidden;position:relative}
.catalog-block details:not([open]) summary .catalog-toggle::after{content:'open';visibility:visible;font-size:0.65rem;position:absolute;right:0}
@if($hoverReveal && $imgFilter !== 'none')
.catalog-block .catalog-img{transition:filter 0.6s ease}
.catalog-block .catalog-img:hover{filter:none !important}
@endif
@media(max-width:768px){
  .catalog-block summary{grid-template-columns:35px 1fr 60px 50px !important;padding:1rem 0 !important}
  .catalog-block summary span:nth-child(2){font-size:0.95rem !important}
  .catalog-block details>div>.catalog-imgs{flex-wrap:nowrap}
  .catalog-block details>div>.catalog-imgs>div{flex:0 0 160px !important}
  .catalog-block details>div>.catalog-text{grid-template-columns:1fr !important}
}
</style>
<div style="margin-bottom:1.5rem;">
    {{-- Header row --}}
    <div style="display:grid;grid-template-columns:50px 1fr 80px 60px;padding:0.75rem 0;border-bottom:2px solid var(--color-text,#201F1D);margin-bottom:0;">
        @foreach($labels as $label)
        <span style="font-family:var(--font-heading,sans-serif);font-size:0.6rem;text-transform:uppercase;letter-spacing:0.15em;color:var(--color-text-muted,#7D7B7A);">{{ $label }}</span>
        @endforeach
    </div>
    <ol style="list-style:none;margin:0;padding:0;">
    @foreach($items as $index => $item)
        <li style="border-bottom:1px solid rgba(201,193,171,0.4);">
            <details style="overflow:hidden;"@if($openFirst && $index === 0) open @endif>
                <summary style="display:grid;grid-template-columns:50px 1fr 80px 60px;align-items:center;padding:1.2rem 0;cursor:pointer;list-style:none;font-family:var(--font-body,sans-serif);">
                    <span style="font-family:var(--font-heading,sans-serif);font-size:0.75rem;color:var(--color-text-muted,#7D7B7A);">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <span style="font-family:var(--font-body,sans-serif);font-size:1.1rem;font-weight:600;letter-spacing:0.02em;">{{ e($item['title'] ?? '') }}</span>
                    <span style="font-size:0.95rem;color:var(--color-text-muted,#7D7B7A);">{{ e($item['subtitle'] ?? '') }}</span>
                    <span class="catalog-toggle" style="font-family:var(--font-heading,sans-serif);font-size:0.65rem;text-transform:uppercase;text-align:right;color:var(--color-text-muted,#7D7B7A);letter-spacing:0.1em;">toggle</span>
                </summary>
                <div style="padding:0 0 2rem;">
                    @if(!empty($item['images']))
                    <div class="catalog-imgs" style="display:flex;gap:12px;margin-bottom:1.5rem;overflow-x:auto;padding-bottom:0.5rem;">
                        @foreach($item['images'] as $img)
                        @php $safeSrc = $safeUrl($img); @endphp
                        @if($safeSrc)
                        <div style="flex:0 0 200px;">
                            <img class="catalog-img" src="{{ e($safeSrc) }}" alt="" loading="lazy" style="width:100%;height:{{ $imgHeight }};object-fit:cover;filter:{{ $filterCss }};">
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @endif
                    <div class="catalog-text" style="display:grid;grid-template-columns:{{ !empty($item['contentSecondary']) ? '1fr 1fr' : '1fr' }};gap:2rem;">
                        <div style="font-family:var(--font-body,sans-serif);font-size:0.9rem;line-height:1.8;color:var(--color-text,#201F1D);">
                            {!! $item['content'] ?? '' !!}
                        </div>
                        @if(!empty($item['contentSecondary']))
                        <div style="font-size:0.85rem;line-height:1.9;color:var(--color-text-muted,#686459);">
                            {!! $item['contentSecondary'] ?? '' !!}
                        </div>
                        @endif
                    </div>
                </div>
            </details>
        </li>
    @endforeach
    </ol>
</div>

</div>
