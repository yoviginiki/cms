@use('App\Support\Blocks\BlockStyle')
@use('App\Domain\Publishing\Services\LocalePaths')
@php
    $__bs = $blockStyle ?? [];
    $__ba = $blockAnimation ?? [];
    $__adv = $blockAdvanced ?? [];
    $__sharedStyle = BlockStyle::buildStyle($__bs, $__ba, $data ?? []);
    $__customClass = BlockStyle::buildClasses($__adv, $__ba);
    $__htmlId = BlockStyle::safeId($__adv['htmlId'] ?? '');

    $languages = LocalePaths::languages($site);
    $default = LocalePaths::defaultLanguage($site);

    $style = in_array($data['style'] ?? 'inline', ['inline', 'dropdown']) ? ($data['style'] ?? 'inline') : 'inline';
    $display = in_array($data['display'] ?? 'code', ['code', 'name', 'flag', 'flag-code', 'flag-name']) ? ($data['display'] ?? 'code') : 'code';
    $flagSize = max(10, min(64, intval($data['flagSize'] ?? 18)));
    $fontSize = max(9, min(48, intval($data['fontSize'] ?? 14)));
    $gapPx = max(2, min(48, intval($data['gap'] ?? 10)));
    $uppercase = $data['uppercase'] ?? true;
    $separator = ['slash' => '/', 'pipe' => '|', 'dot' => '·', 'none' => ''][$data['separator'] ?? 'none'] ?? '';
    $alignment = in_array($data['alignment'] ?? 'left', ['left', 'center', 'right']) ? ($data['alignment'] ?? 'left') : 'left';
    $justify = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$alignment];
    $textColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', $data['textColor'] ?? '') ? $data['textColor'] : 'var(--color-text-muted,#6b7280)';
    $activeColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', $data['activeColor'] ?? '') ? $data['activeColor'] : 'var(--color-text,#1f2937)';

    $__scope = 'lsw-' . substr(md5($__htmlId ?: uniqid('', true)), 0, 8);

    // Label for one language per the display mode
    $labelFor = function (string $lang) use ($display, $flagSize, $uppercase) {
        $meta = LocalePaths::languageMeta($lang);
        $code = $uppercase ? strtoupper($lang) : $lang;
        $flag = '<span style="font-size:' . $flagSize . 'px;line-height:1;" aria-hidden="true">' . $meta['flag'] . '</span>';
        return match ($display) {
            'code' => e($code),
            'name' => e($meta['native']),
            'flag' => $flag . '<span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);">' . e($meta['native']) . '</span>',
            'flag-code' => $flag . '<span>' . e($code) . '</span>',
            'flag-name' => $flag . '<span>' . e($meta['native']) . '</span>',
        };
    };
    $itemStyle = 'display:inline-flex;align-items:center;gap:0.35em;color:' . $textColor . ';text-decoration:none;font-size:' . $fontSize . 'px;';
@endphp
@if(count($languages) > 1)
<style>
.{{ $__scope }} a.is-current{color:{{ $activeColor }};font-weight:700;}
.{{ $__scope }} details{position:relative;display:inline-block;}
.{{ $__scope }} summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:0.35em;font-size:{{ $fontSize }}px;color:{{ $activeColor }};}
.{{ $__scope }} summary::-webkit-details-marker{display:none;}
.{{ $__scope }} summary::after{content:"▾";font-size:0.75em;opacity:0.6;}
.{{ $__scope }} .lsw-menu{position:absolute;top:calc(100% + 6px);{{ $alignment === 'right' ? 'right:0;' : 'left:0;' }}z-index:1200;min-width:100%;display:flex;flex-direction:column;gap:2px;padding:6px;border-radius:10px;background:var(--color-bg,#fff);border:1px solid var(--color-border,#e5e7eb);box-shadow:0 8px 24px rgba(0,0,0,0.12);white-space:nowrap;}
.{{ $__scope }} .lsw-menu a{padding:5px 10px;border-radius:6px;}
.{{ $__scope }} .lsw-menu a:hover{background:var(--color-bg-secondary,#f3f4f6);}
</style>
<div class="langswitcher-block lang-switch-block {{ $__scope }} {{ $__customClass }}"
     data-langs="{{ implode(',', $languages) }}" data-default="{{ $default }}"
     style="display:flex;justify-content:{{ $justify }};align-items:center;{{ $__sharedStyle }}"
     @if($__htmlId) id="{{ $__htmlId }}" @endif
     aria-label="Language">
@if($style === 'dropdown')
    <details>
        <summary><span data-lang-label>{!! $labelFor($default) !!}</span></summary>
        <div class="lsw-menu">
            @foreach($languages as $lang)
                <a href="/{{ $lang === $default ? '' : $lang . '/' }}" data-lang="{{ $lang }}" hreflang="{{ $lang }}" style="{{ $itemStyle }}">{!! $labelFor($lang) !!}</a>
            @endforeach
        </div>
    </details>
@else
    <nav style="display:flex;align-items:center;gap:{{ $gapPx }}px;">
        @foreach($languages as $lang)
            @if(!$loop->first && $separator !== '')
                <span style="color:{{ $textColor }};opacity:0.5;font-size:{{ $fontSize }}px;" aria-hidden="true">{{ $separator }}</span>
            @endif
            <a href="/{{ $lang === $default ? '' : $lang . '/' }}" data-lang="{{ $lang }}" hreflang="{{ $lang }}" style="{{ $itemStyle }}">{!! $labelFor($lang) !!}</a>
        @endforeach
    </nav>
@endif
</div>
<script>
(function(){
  document.querySelectorAll('.lang-switch-block:not([data-init])').forEach(function(el){
    el.dataset.init = '1';
    var langs = (el.dataset.langs || '').split(',');
    var def = el.dataset.default;
    var seg = location.pathname.split('/')[1];
    var current = (langs.indexOf(seg) > -1 && seg !== def) ? seg : def;
    var alt = {};
    document.querySelectorAll('link[rel="alternate"][hreflang]').forEach(function(l){
      alt[l.getAttribute('hreflang')] = l.getAttribute('href');
    });
    el.querySelectorAll('a[data-lang]').forEach(function(a){
      var lg = a.dataset.lang;
      if (alt[lg]) a.setAttribute('href', alt[lg]);
      if (lg === current) { a.classList.add('is-current'); a.setAttribute('aria-current', 'true'); }
    });
    var sum = el.querySelector('summary [data-lang-label]');
    var cur = el.querySelector('a[data-lang="' + current + '"]');
    if (sum && cur) sum.innerHTML = cur.innerHTML;
  });
})();
</script>
@endif
