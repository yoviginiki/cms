<?php

namespace App\Domain\Blocks\Definitions;

class BreadcrumbsBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'breadcrumbs'; }
    public function category(): string { return 'navigation'; }

    public function validationRules(): array
    {
        return [
            'separator'   => ['sometimes', 'nullable', 'string', 'max:10'],
            'showHome'    => ['sometimes', 'boolean'],
            'homeLabel'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'showCurrent' => ['sometimes', 'boolean'],
            'schema'      => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
