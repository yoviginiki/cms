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
<div class="customform-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $fields = $data['fields'] ?? [];
    $submitText = $data['submitText'] ?? 'Send';
    $endpoint = $data['endpoint'] ?? '';
    $successMessage = $data['successMessage'] ?? 'Thank you!';
@endphp
<form action="{{ $endpoint }}" method="POST" class="space-y-4" data-success-message="{{ $successMessage }}">
    @csrf
    @foreach($fields as $field)
        @php
            $type = $field['type'] ?? 'text';
            $label = $field['label'] ?? '';
            $required = !empty($field['required']);
            $placeholder = $field['placeholder'] ?? '';
            $name = \Illuminate\Support\Str::slug($label, '_');
        @endphp
        <div class="form-control w-full">
            <label class="label">
                <span class="label-text">{{ $label }}@if($required) <span style="color:#ef4444;">*</span>@endif</span>
            </label>
            @if($type === 'textarea')
                <textarea name="{{ $name }}" placeholder="{{ $placeholder }}" class="textarea textarea-bordered w-full" @if($required) required @endif></textarea>
            @elseif($type === 'select')
                <select name="{{ $name }}" class="select select-bordered w-full" @if($required) required @endif>
                    <option value="" disabled selected>{{ $placeholder ?: 'Select...' }}</option>
                </select>
            @elseif($type === 'checkbox')
                <label class="cursor-pointer flex items-center gap-2">
                    <input type="checkbox" name="{{ $name }}" class="checkbox" @if($required) required @endif />
                    <span class="label-text">{{ $placeholder ?: $label }}</span>
                </label>
            @elseif($type === 'radio')
                <label class="cursor-pointer flex items-center gap-2">
                    <input type="radio" name="{{ $name }}" class="radio" @if($required) required @endif />
                    <span class="label-text">{{ $placeholder ?: $label }}</span>
                </label>
            @elseif($type === 'file')
                <input type="file" name="{{ $name }}" class="file-input file-input-bordered w-full" @if($required) required @endif />
            @else
                <input type="{{ $type }}" name="{{ $name }}" placeholder="{{ $placeholder }}" class="input input-bordered w-full" @if($required) required @endif />
            @endif
        </div>
    @endforeach
    <button type="submit" class="btn btn-primary">{{ $submitText }}</button>
</form>

</div>