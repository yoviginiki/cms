<?php

namespace App\Domain\Blocks\Definitions;

class SpacerBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'spacer'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'height' => ['sometimes', 'string', 'max:20'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
