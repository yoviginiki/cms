<?php

namespace App\Domain\Blocks\Definitions;

class RowBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'row'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        $cssDim = 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/';

        return [
            'layout' => ['sometimes', 'nullable', 'in:1,1/2+1/2,1/3+2/3,2/3+1/3,1/3+1/3+1/3,1/4+1/4+1/4+1/4,1/4+3/4,3/4+1/4'],
            'gap' => ['sometimes', 'nullable', 'string', 'max:20', $cssDim],
            'max_width' => ['sometimes', 'nullable', 'string', 'max:20', $cssDim],
            'vertical_align' => ['sometimes', 'nullable', 'in:start,center,end,stretch'],
            // P5: per-column widths on a 12-unit grid (overrides `layout` when set)
            'col_spans' => ['sometimes', 'nullable', 'array', 'max:6'],
            'col_spans.*' => ['integer', 'min:1', 'max:12'],
            // P5: mobile stack order — a permutation of 0-based column indices
            'stack_order' => ['sometimes', 'nullable', 'array', 'max:6'],
            'stack_order.*' => ['integer', 'min:0', 'max:5'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 6; }
}
