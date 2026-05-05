<?php

namespace App\Domain\Blocks\Definitions;

class TabsBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'tabs'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'tab_labels' => ['required', 'array', 'min:1'],
            'tab_labels.*' => ['required', 'string', 'max:100'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => '',
        ];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 10; }
}
