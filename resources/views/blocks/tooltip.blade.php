@php
    $triggerText = $data['triggerText'] ?? 'Hover me';
    $tooltipText = $data['tooltipText'] ?? 'Tooltip content';
    $position = $data['position'] ?? 'top';
    $posClass = match($position) {
        'bottom' => 'tooltip-bottom',
        'left' => 'tooltip-left',
        'right' => 'tooltip-right',
        default => 'tooltip-top',
    };
@endphp
<span class="tooltip {{ $posClass }}" data-tip="{{ e($tooltipText) }}" style="display: inline-block;">
    <span style="border-bottom: 1px dashed currentColor; cursor: help;">{{ e($triggerText) }}</span>
</span>
