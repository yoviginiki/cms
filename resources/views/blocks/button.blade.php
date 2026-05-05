@php
    $style = $data['style'] ?? 'primary';
    $size = $data['size'] ?? 'md';
    $target = ($data['target'] ?? '_self') !== '_self' ? ' target="' . e($data['target']) . '" rel="noopener"' : '';
    $sizeClass = match($size) { 'sm' => 'btn-sm', 'lg' => 'btn-lg', default => 'btn-md' };
    $styleClass = match($style) { 'secondary' => 'btn-secondary', 'outline' => 'btn-outline', 'ghost' => 'btn-ghost', default => 'btn-primary' };
@endphp
<div class="button-block" style="margin-bottom: var(--space-6);">
    <a href="{{ e($data['url'] ?? '#') }}" class="{{ $styleClass }} {{ $sizeClass }}"{!! $target !!}>{{ $data['text'] ?? 'Button' }}</a>
</div>
