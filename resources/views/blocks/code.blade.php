@php
    $lang = $data['language'] ?? '';
    $code = e($data['code'] ?? '');
@endphp
<div class="code-block" style="margin-bottom: 1.5rem;">
    <pre style="background: #1e293b; color: #e2e8f0; padding: 1.25rem; border-radius: 0.5rem; overflow-x: auto; font-size: 0.875rem; line-height: 1.6;"><code class="language-{{ $lang }}">{{ $data['code'] ?? '' }}</code></pre>
</div>
