<?php

namespace App\Domain\Blocks\Definitions;

class NewsletterBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'newsletter'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'heading'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'buttonText'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'endpoint'    => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'style'       => ['sometimes', 'in:inline,card,full-width'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
