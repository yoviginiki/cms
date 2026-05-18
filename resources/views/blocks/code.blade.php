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
<div class="code-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif @if(!empty($__adv['ariaLabel'])) aria-label="{{ $__adv['ariaLabel'] }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $lang = $data['language'] ?? '';
    $code = $data['code'] ?? '';
    $showLineNumbers = !empty($data['show_line_numbers']);
    $lines = explode("\n", $code);
@endphp
@if($lang)
<div style="background:#1e293b;border-radius:var(--border-radius-md,0.5rem) 0.5rem 0 0;padding:0.5rem 1rem;">
    <span style="font-size:0.75rem;color:#94a3b8;font-family:var(--font-mono,monospace);">{{ $lang }}</span>
</div>
@endif
<pre style="background:#1e293b;color:#e2e8f0;padding:1.25rem;{{ $lang ? 'border-radius:0 0 0.5rem 0.5rem;' : 'border-radius:var(--border-radius-md,0.5rem);' }}overflow-x:auto;font-size:0.875rem;line-height:1.6;margin:0;position:relative;{{ $showLineNumbers ? 'padding-left:3.5rem;' : '' }}">@if($showLineNumbers)<span style="position:absolute;left:0;top:1.25rem;width:2.5rem;text-align:right;color:#475569;font-size:0.75rem;line-height:1.6;user-select:none;">@foreach($lines as $i => $line){{ $i + 1 }}
@endforeach</span>@endif<code class="language-{{ e($lang) }}">{{ $code }}</code></pre>

</div>