<?php

namespace App\Domain\Blocks\Definitions;

class PostVideoBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'post-video'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'aspectRatio' => ['sometimes', 'in:16:9,4:3,1:1'],
            'autoplay' => ['sometimes', 'boolean'],
            'controls' => ['sometimes', 'boolean']
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
