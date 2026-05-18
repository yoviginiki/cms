<?php

namespace App\Domain\Blocks\Definitions;

class PostContentBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'post-content'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
