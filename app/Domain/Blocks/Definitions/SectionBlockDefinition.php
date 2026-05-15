<?php

namespace App\Domain\Blocks\Definitions;

class SectionBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'section'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'background_color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'background_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'padding' => ['sometimes', 'nullable', 'in:none,sm,md,lg,xl'], // legacy preset
            'padding_top' => ['sometimes', 'nullable', 'string', 'max:20'],
            'padding_bottom' => ['sometimes', 'nullable', 'string', 'max:20'],
            'max_width' => ['sometimes', 'nullable', 'string', 'max:20'],
            'anchor_id' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]*$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
