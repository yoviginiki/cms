<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G2 slot block: renders the current record's title inside a
 * record-single template (context: $__record).
 */
class RecordTitleBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'record-title'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'tag' => ['sometimes', 'in:h1,h2,h3,h4,h5,h6'],
            'fontSize' => ['sometimes', 'nullable', 'string', 'max:20'],
            'textAlign' => ['sometimes', 'nullable', 'in:,left,center,right'],
            'color' => ['sometimes', 'nullable', 'string', 'max:40'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
