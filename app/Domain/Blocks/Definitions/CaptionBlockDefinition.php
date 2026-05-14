<?php

namespace App\Domain\Blocks\Definitions;

class CaptionBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'caption'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'text'   => ['sometimes', 'string', 'max:500'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
