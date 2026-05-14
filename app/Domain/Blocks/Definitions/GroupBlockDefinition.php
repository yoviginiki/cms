<?php

namespace App\Domain\Blocks\Definitions;

class GroupBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'group'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'tag' => ['sometimes', 'in:div,section,article'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
