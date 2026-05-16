<?php

namespace App\Domain\Blocks\Definitions;

class ColumnBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'column'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        $cssDim = 'regex:/^\d+(\.\d+)?(px|rem|em|%|vh|vw)$/';

        return [
            'padding' => ['sometimes', 'nullable', 'string', 'max:20', $cssDim],
            'vertical_align' => ['sometimes', 'nullable', 'in:start,center,end,stretch'],
            'background_color' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[#a-zA-Z0-9(),.\s]*$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
