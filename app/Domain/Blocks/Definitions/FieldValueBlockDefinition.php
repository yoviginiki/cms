<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G2 slot block: renders ANY schema field of the current record,
 * type-appropriately (price formatting, linked relations, gallery, …) via
 * RecordDisplay. Context: $__record + $__collection.
 */
class FieldValueBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'field-value'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'field' => ['sometimes', 'nullable', 'string', 'max:40'],
            'showLabel' => ['sometimes', 'boolean'],
            'labelText' => ['sometimes', 'nullable', 'string', 'max:80'],
            'emptyText' => ['sometimes', 'nullable', 'string', 'max:80'],
            'textAlign' => ['sometimes', 'nullable', 'in:,left,center,right'],
            'fontSize' => ['sometimes', 'nullable', 'string', 'max:20'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
