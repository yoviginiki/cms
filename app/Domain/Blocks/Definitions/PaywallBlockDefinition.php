<?php

namespace App\Domain\Blocks\Definitions;

class PaywallBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'paywall'; }
    public function category(): string { return 'commerce'; }

    public function validationRules(): array
    {
        return [
            'previewLines'  => ['sometimes', 'integer', 'min:0', 'max:20'],
            'blurIntensity' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'heading'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'ctaText'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'ctaUrl'        => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
