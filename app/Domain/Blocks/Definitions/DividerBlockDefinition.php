<?php

namespace App\Domain\Blocks\Definitions;

class DividerBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'divider'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
