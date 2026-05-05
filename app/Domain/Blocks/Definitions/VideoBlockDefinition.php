<?php

namespace App\Domain\Blocks\Definitions;

class VideoBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'video'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
            'autoplay' => ['sometimes', 'boolean'],
            'muted' => ['sometimes', 'boolean'],
            'poster' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
