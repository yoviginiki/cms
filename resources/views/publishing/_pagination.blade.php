@php
    $__cp = (int) ($currentPage ?? 1);
    $__tp = (int) ($totalPages ?? 1);
    $__pp = $paginationBase ?? null;
    $__lnk = 'padding:var(--space-2,0.5rem) var(--space-3,0.75rem);border:1px solid var(--color-border,#e5e7eb);border-radius:var(--border-radius-md,8px);text-decoration:none;color:inherit;';
    $__cur = 'padding:var(--space-2,0.5rem) var(--space-3,0.75rem);background:var(--color-primary,#3b82f6);color:#fff;border-radius:var(--border-radius-md,8px);';
    // Precompute the windowed page list (first, current±2, last) with null gaps.
    $__items = [];
    if ($__pp && $__tp > 1) {
        $lo = max(1, $__cp - 2);
        $hi = min($__tp, $__cp + 2);
        if ($lo > 1) { $__items[] = 1; if ($lo > 2) { $__items[] = null; } }
        for ($i = $lo; $i <= $hi; $i++) { $__items[] = $i; }
        if ($hi < $__tp) { if ($hi < $__tp - 1) { $__items[] = null; } $__items[] = $__tp; }
    }
    $__url = fn ($i) => $i <= 1 ? "{$__pp}/" : "{$__pp}/page/{$i}/";
@endphp
@if($__pp && $__tp > 1)
<nav aria-label="Pagination" style="padding:var(--space-8,2rem) 0;display:flex;gap:var(--space-2,0.5rem);justify-content:center;flex-wrap:wrap;align-items:center;">
    @if($__cp > 1)
        <a rel="prev" href="{{ $__url($__cp - 1) }}" style="{{ $__lnk }}">&lsaquo;</a>
    @endif
    @foreach($__items as $__it)
        @if($__it === null)
            <span aria-hidden="true">&hellip;</span>
        @elseif($__it === $__cp)
            <span aria-current="page" style="{{ $__cur }}">{{ $__it }}</span>
        @else
            <a href="{{ $__url($__it) }}" style="{{ $__lnk }}">{{ $__it }}</a>
        @endif
    @endforeach
    @if($__cp < $__tp)
        <a rel="next" href="{{ $__url($__cp + 1) }}" style="{{ $__lnk }}">&rsaquo;</a>
    @endif
</nav>
@endif
