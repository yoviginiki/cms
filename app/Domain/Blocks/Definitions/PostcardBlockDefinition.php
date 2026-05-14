<?php

namespace App\Domain\Blocks\Definitions;

class PostcardBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'postcard'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'postId'       => ['sometimes', 'nullable', 'string', 'max:36'],
            'style'        => ['sometimes', 'in:vertical,horizontal'],
            'showExcerpt'  => ['sometimes', 'boolean'],
            'showDate'     => ['sometimes', 'boolean'],
            'showCategory' => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
