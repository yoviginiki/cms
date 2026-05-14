<?php

namespace App\Domain\Blocks\Definitions;

class PricingcardBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'pricingcard'; }
    public function category(): string { return 'commerce'; }

    public function validationRules(): array
    {
        return [
            'planName'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'price'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'period'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'features'          => ['sometimes', 'array'],
            'features.*.text'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'features.*.included' => ['sometimes', 'boolean'],
            'ctaText'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'ctaUrl'      => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'highlighted' => ['sometimes', 'boolean'],
            'badge'       => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
