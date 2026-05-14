<?php

namespace App\Domain\Blocks\Definitions;

class TooltipBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'tooltip'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'triggerText' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tooltipText' => ['sometimes', 'nullable', 'string', 'max:500'],
            'position'    => ['sometimes', 'in:top,bottom,left,right'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
