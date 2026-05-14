@use('App\Support\Blocks\BlockStyle')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__resp = $blockResponsive ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba);
    $__customClass = BlockStyle::safeClass($__adv['customClass'] ?? '');
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');
    $__animAttr = BlockStyle::animationAttr($__ba);
    $__hideOn = BlockStyle::buildHideOnCss($__resp, $__htmlId);
@endphp
@if($__hideOn['css'])<style>{{ $__hideOn['css'] }}</style>@endif
<div class="contact-form-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
@php
    $fields = $data['fields'] ?? [['label' => 'Name', 'type' => 'text', 'required' => true], ['label' => 'Email', 'type' => 'email', 'required' => true], ['label' => 'Message', 'type' => 'textarea', 'required' => true]];
    $submitLabel = $data['submit_label'] ?? 'Send Message';
    $successMsg = $data['success_message'] ?? 'Thank you! Your message has been sent.';
@endphp
<div class="contact-form-block" style="margin-bottom: 1.5rem;">
    <form method="POST" action="/api/v1/sites/{{ $site->id }}/forms/submit"
          style="max-width: 600px;"
          onsubmit="event.preventDefault();const f=this;const fd=new FormData(f);fetch(f.action,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{f.innerHTML='<p style=\'color:#16a34a;font-weight:500;\'>{{ e($successMsg) }}</p>'}).catch(e=>{alert('Error sending message')})">
        <input type="text" name="_honeypot" style="display:none" tabindex="-1" autocomplete="off">
        @foreach($fields as $field)
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.25rem;">
                    {{ $field['label'] }}@if(!empty($field['required'])) <span style="color: #ef4444;">*</span>@endif
                </label>
                @if($field['type'] === 'textarea')
                    <textarea name="{{ \Illuminate\Support\Str::slug($field['label']) }}" rows="4"{{ !empty($field['required']) ? ' required' : '' }}
                              style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-family: inherit;"></textarea>
                @else
                    <input type="{{ $field['type'] }}" name="{{ \Illuminate\Support\Str::slug($field['label']) }}"{{ !empty($field['required']) ? ' required' : '' }}
                           style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                @endif
            </div>
        @endforeach
        <button type="submit" style="display: inline-block; padding: 0.625rem 1.5rem; background: var(--color-primary, #3b82f6); color: #fff; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer;">
            {{ $submitLabel }}
        </button>
    </form>
</div>

</div>