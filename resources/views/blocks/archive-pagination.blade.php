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
<div class="archive-pagination-block {{ $__customClass }} {{ $__hideOn['scopeClass'] }}" style="position:relative;{{ $__sharedStyle }}" @if($__htmlId) id="{{ $__htmlId }}" @endif @if($__animAttr) data-animation="{{ $__animAttr }}" @endif>
{!! \App\Support\Blocks\BlockStyle::buildOverlayHtml($data ?? []) !!}
@php
    $style = $data['style'] ?? 'numbered';
    $align = in_array($data['align'] ?? 'center', ['left','center','right']) ? ($data['align'] ?? 'center') : 'center';

    $currentPage = $__archiveCurrentPage ?? 1;
    $totalPages = $__archiveTotalPages ?? 1;
    $baseUrl = $__archiveBaseUrl ?? '';
@endphp
@if($totalPages > 1)
<nav style="text-align:{{ $align }};margin-top:2rem;">
    @if($style === 'numbered')
    <div style="display:inline-flex;gap:0.25rem;">
        @for($p = 1; $p <= $totalPages; $p++)
            @if($p === $currentPage)
                <span style="padding:0.375rem 0.75rem;background:var(--color-accent,#2ea3f2);color:#fff;border-radius:var(--border-radius-sm,3px);font-size:0.875rem;">{{ $p }}</span>
            @else
                <a href="{{ $baseUrl }}{{ $p > 1 ? '/page/' . $p : '' }}" style="padding:0.375rem 0.75rem;background:var(--color-bg-alt,#f5f5f5);color:var(--color-text,#666);border-radius:var(--border-radius-sm,3px);font-size:0.875rem;text-decoration:none;">{{ $p }}</a>
            @endif
        @endfor
    </div>
    @elseif($style === 'simple')
    <div style="display:flex;justify-content:{{ $align === 'center' ? 'center' : ($align === 'right' ? 'flex-end' : 'flex-start') }};gap:2rem;">
        @if($currentPage > 1)
            <a href="{{ $baseUrl }}{{ $currentPage > 2 ? '/page/' . ($currentPage - 1) : '' }}" style="color:var(--color-accent,#2ea3f2);text-decoration:none;">&larr; Previous</a>
        @endif
        @if($currentPage < $totalPages)
            <a href="{{ $baseUrl }}/page/{{ $currentPage + 1 }}" style="color:var(--color-accent,#2ea3f2);text-decoration:none;">Next &rarr;</a>
        @endif
    </div>
    @endif
</nav>
@endif

</div>
