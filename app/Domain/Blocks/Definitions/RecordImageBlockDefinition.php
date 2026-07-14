<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G2 slot block: renders an image field of the current record
 * (context: $__record + $__collection). `field` picks the schema key;
 * empty = first image field in the schema.
 */
class RecordImageBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'record-image'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'field' => ['sometimes', 'nullable', 'string', 'max:40'],
            'aspectRatio' => ['sometimes', 'in:auto,16:9,4:3,1:1,3:2'],
            'objectFit' => ['sometimes', 'in:cover,contain'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
