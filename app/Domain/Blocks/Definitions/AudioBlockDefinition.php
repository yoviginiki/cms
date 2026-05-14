<?php

namespace App\Domain\Blocks\Definitions;

class AudioBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'audio'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'url'    => ['sometimes', 'nullable', 'string', 'max:2048'],
            'title'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'artist' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
