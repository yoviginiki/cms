<?php

namespace App\Domain\Blocks\Definitions;

class PricingtableBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'pricingtable'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'plans'                => ['sometimes', 'array'],
            'plans.*.name'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'plans.*.price'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'plans.*.period'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'plans.*.features'     => ['sometimes', 'array'],
            'plans.*.features.*'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'plans.*.ctaText'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'plans.*.ctaUrl'       => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'plans.*.highlighted'  => ['sometimes', 'boolean'],
            'columns'              => ['sometimes', 'integer', 'min:1', 'max:6'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
