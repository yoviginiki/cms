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
    $assetId = ($record && $fieldKey) ? ($record->data[$fieldKey] ?? null) : null;
    $src = ($assetId && $site) ? RecordDisplay::assetUrl($site, is_string($assetId) ? $assetId : null) : null;

    $ratioMap = ['16:9' => '56.25%', '4:3' => '75%', '1:1' => '100%', '3:2' => '66.67%'];
    $ratio = $ratioMap[$data['aspectRatio'] ?? ''] ?? null;
    $fit = in_array($data['objectFit'] ?? 'cover', ['cover','contain']) ? ($data['objectFit'] ?? 'cover') : 'cover';
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="record-image-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@if($src)
    @if($ratio)
        <div style="position:relative;width:100%;padding-top:{{ $ratio }};overflow:hidden;">
            <img src="{{ $src }}" alt="{{ $record?->title ?? '' }}" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:{{ $fit }};">
        </div>
    @else
        <img src="{{ $src }}" alt="{{ $record?->title ?? '' }}" loading="lazy" style="max-width:100%;height:auto;display:block;">
    @endif
@endif
</div>
