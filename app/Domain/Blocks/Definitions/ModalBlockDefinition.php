<?php

namespace App\Domain\Blocks\Definitions;

class ModalBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'modal'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'triggerText' => ['sometimes', 'nullable', 'string', 'max:100'],
            'title'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'size'        => ['sometimes', 'in:sm,md,lg'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
