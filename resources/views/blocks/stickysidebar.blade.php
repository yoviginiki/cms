@php
    $side = $data['sidebarSide'] ?? 'right';
    $sidebarW = $data['sidebarWidth'] ?? '300px';
    $gap = $data['gap'] ?? '32px';
    $offset = $data['stickyOffset'] ?? '80px';
@endphp
<div class="stickysidebar-block" style="display: flex; gap: {{ $gap }};{{ $side === 'left' ? ' flex-direction: row-reverse;' : '' }}">
    <div style="flex: 1; min-width: 0;">
        {!! $children !!}
    </div>
    <aside style="width: {{ $sidebarW }}; flex-shrink: 0; position: sticky; top: {{ $offset }}; align-self: flex-start;">
        {{-- Sidebar content placed via children or slots --}}
    </aside>
</div>
