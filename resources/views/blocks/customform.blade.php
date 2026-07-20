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
    // S5 Forms v2 — the published page stays static: a plain <form> POSTs to
    // the platform origin; the server re-validates against THIS block's
    // fields (looked up by formKey). No-JS gets a 303 back to the page with
    // the :target success anchor; JS sets the _t time trap.
    $fields = $data['fields'] ?? [];
    $submitText = $data['submitText'] ?? 'Send';
    $successMessage = $data['successMessage'] ?? 'Thank you!';
    $formKey = preg_match('/^[a-z0-9\-_]{1,80}$/', (string) ($data['formKey'] ?? '')) ? $data['formKey'] : '';
    $action = $formKey
        ? rtrim((string) config('app.url'), '/') . "/api/v1/sites/{$site->id}/forms/{$formKey}/submit"
        : '';
@endphp
@if(!$formKey || $fields === [])
    <p style="opacity:.5;padding:1rem;border:1px dashed var(--color-border,#ddd);">This form isn't configured yet — set its fields and key in the editor.</p>
@else
    <div id="form-{{ $formKey }}-success" class="customform-success" style="display:none;padding:.9rem 1.1rem;border:1px solid var(--color-success,#16a34a);color:var(--color-success,#16a34a);margin-bottom:1rem;">{{ $successMessage }}</div>
    <style>#form-{{ $formKey }}-success:target{display:block;} #form-{{ $formKey }}-success:target + form{display:none;}</style>
    <form action="{{ $action }}" method="POST" class="space-y-4" data-form-key="{{ $formKey }}" data-success-message="{{ $successMessage }}">
        <input type="text" name="_honeypot" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="_t" value="">
        <script>document.currentScript.previousElementSibling.value = Date.now();</script>
        @foreach($fields as $field)
            @php
                $type = $field['type'] ?? 'text';
                $label = $field['label'] ?? '';
                $required = !empty($field['required']);
                $placeholder = $field['placeholder'] ?? '';
                $options = array_values(array_filter(array_map('strval', (array) ($field['options'] ?? []))));
                $name = \App\Domain\Forms\Services\FormSubmissionService::fieldName($label);
            @endphp
            @continue($label === '')
            <div class="form-control w-full" style="margin-bottom:1rem;">
                @if($type !== 'checkbox')
                    <label class="label" style="display:block;font-size:.875rem;font-weight:500;margin-bottom:.25rem;">
                        <span class="label-text">{{ $label }}@if($required) <span style="color:var(--color-danger,#ef4444);">*</span>@endif</span>
                    </label>
                @endif
                @if($type === 'textarea')
                    <textarea name="{{ $name }}" placeholder="{{ $placeholder }}" rows="4" class="textarea textarea-bordered w-full" style="width:100%;padding:.5rem .75rem;border:1px solid var(--input-border,#d1d5db);font-family:inherit;" @if($required) required @endif></textarea>
                @elseif($type === 'select')
                    <select name="{{ $name }}" class="select select-bordered w-full" style="width:100%;padding:.5rem .75rem;border:1px solid var(--input-border,#d1d5db);" @if($required) required @endif>
                        <option value="" disabled selected>{{ $placeholder ?: 'Select…' }}</option>
                        @foreach($options as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                @elseif($type === 'checkbox')
                    <label class="cursor-pointer flex items-center gap-2" style="display:flex;align-items:center;gap:.5rem;">
                        <input type="checkbox" name="{{ $name }}" value="on" class="checkbox" @if($required) required @endif />
                        <span class="label-text">{{ $placeholder ?: $label }}</span>
                    </label>
                @elseif($type === 'radio')
                    @foreach($options !== [] ? $options : [$placeholder ?: $label] as $option)
                        <label class="cursor-pointer flex items-center gap-2" style="display:flex;align-items:center;gap:.5rem;margin-bottom:.25rem;">
                            <input type="radio" name="{{ $name }}" value="{{ $option }}" class="radio" @if($required && $loop->first) required @endif />
                            <span class="label-text">{{ $option }}</span>
                        </label>
                    @endforeach
                @else
                    <input type="{{ in_array($type, ['text','email'], true) ? $type : 'text' }}" name="{{ $name }}" placeholder="{{ $placeholder }}" class="input input-bordered w-full" style="width:100%;padding:.5rem .75rem;border:1px solid var(--input-border,#d1d5db);" @if($required) required @endif />
                @endif
            </div>
        @endforeach
        <button type="submit" class="btn btn-primary" style="display:inline-block;padding:.625rem 1.5rem;background:var(--color-primary,#3b82f6);color:var(--color-text-inverse,#fff);border:none;font-weight:600;cursor:pointer;">{{ $submitText }}</button>
    </form>
@endif

</div>