<?php

namespace App\Domain\Blocks\Definitions;

class HeroBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'hero'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:500'],
            'background_image' => ['sometimes', 'nullable', 'string'],
            'cta_text' => ['sometimes', 'nullable', 'string', 'max:100'],
            'cta_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
