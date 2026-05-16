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
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 6; }
}
