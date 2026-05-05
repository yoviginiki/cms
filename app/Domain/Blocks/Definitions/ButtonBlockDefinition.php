<?php

namespace App\Domain\Blocks\Definitions;

class ButtonBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'button'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'text' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048'],
            'style' => ['sometimes', 'in:primary,secondary,outline,ghost'],
            'size' => ['sometimes', 'in:sm,md,lg'],
            'target' => ['sometimes', 'in:_self,_blank'],
            'icon' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
