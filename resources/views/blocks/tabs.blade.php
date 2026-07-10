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
<div class="tabs-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php $labels = $data['tab_labels'] ?? ['Tab 1']; @endphp
<div class="tabs-block" style="margin-bottom: 1.5rem;">
    <div role="tablist" style="display: flex; border-bottom: 2px solid var(--color-border,#e2e8f0); gap: 0;">
        @foreach($labels as $i => $label)
            <button role="tab" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                    aria-controls="tab-panel-{{ $i }}" id="tab-{{ $i }}"
                    onclick="this.parentElement.querySelectorAll('[role=tab]').forEach(t=>{t.setAttribute('aria-selected','false');t.style.borderBottomColor='transparent';t.style.color='var(--color-text-muted,#6b7280)'});this.setAttribute('aria-selected','true');this.style.borderBottomColor='var(--color-primary,#3b82f6)';this.style.color='var(--color-primary,#3b82f6)';this.closest('.tabs-block').querySelectorAll('[role=tabpanel]').forEach(p=>p.hidden=true);document.getElementById('tab-panel-'+this.id.split('-')[1]).hidden=false;"
                    style="padding: 0.75rem 1.25rem; border: none; border-bottom: 2px solid {{ $i === 0 ? 'var(--color-primary,#3b82f6)' : 'transparent' }}; background: none; cursor: pointer; font-weight: 500; color: {{ $i === 0 ? 'var(--color-primary,#3b82f6)' : 'var(--color-text-muted,#6b7280)' }}; font-size: 0.875rem;">
                {{ $label }}
            </button>
        @endforeach
    </div>
    @foreach($labels as $i => $label)
        <div role="tabpanel" id="tab-panel-{{ $i }}" aria-labelledby="tab-{{ $i }}"{{ $i > 0 ? ' hidden' : '' }}
             style="padding: 1.5rem 0;">
            @if($i === 0){!! $children !!}@endif
        </div>
    @endforeach
</div>

</div>