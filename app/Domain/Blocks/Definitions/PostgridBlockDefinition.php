<?php

namespace App\Domain\Blocks\Definitions;

class PostgridBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'postgrid'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'categoryId'  => ['sometimes', 'nullable', 'string', 'max:36'],
            'limit'       => ['sometimes', 'integer', 'min:1', 'max:50'],
            'columns'     => ['sometimes', 'integer', 'min:1', 'max:6'],
            'cardStyle'   => ['sometimes', 'in:vertical,horizontal'],
            'showExcerpt' => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
