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

    $cssDim = fn($v) => preg_match('/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/i', trim((string) $v)) ? trim((string) $v) : '';
    $record = $__record ?? null;
    $collection = $__collection ?? null;
    $fieldKey = (string) ($data['field'] ?? '');
    $field = ($collection && $fieldKey) ? $collection->field($fieldKey) : null;

    $valueHtml = ($record && $collection && $field)
        ? RecordDisplay::display($site, $collection, $record, $fieldKey)
        : ($fieldKey !== '' ? '<span style="opacity:.4">[' . e($fieldKey) . ']</span>' : '');

    $showLabel = (bool) ($data['showLabel'] ?? false);
    $label = trim((string) ($data['labelText'] ?? '')) ?: ($field['label'] ?? $fieldKey);
    $emptyText = trim((string) ($data['emptyText'] ?? ''));
    $textAlign = in_array($data['textAlign'] ?? '', ['left','center','right']) ? $data['textAlign'] : '';
    $fontSize = $cssDim($data['fontSize'] ?? '');
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
@if($valueHtml !== '' || $emptyText !== '')
<div class="field-value-block field-value-{{ $fieldKey }} {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}{{ $textAlign ? "text-align:{$textAlign};" : '' }}{{ $fontSize ? "font-size:{$fontSize};" : '' }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! BlockStyle::buildOverlayHtml($data ?? []) !!}
@if($showLabel && $label)<span class="field-value-label" style="font-weight:600;margin-right:.5em;">{{ $label }}:</span>@endif
@if($valueHtml !== ''){!! $valueHtml !!}@else<span style="opacity:.5">{{ $emptyText }}</span>@endif
</div>
@endif
