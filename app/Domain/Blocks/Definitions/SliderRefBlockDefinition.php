<?php

namespace App\Domain\Blocks\Definitions;

/**
 * The page-side embed of a slider library entity: a picker + optional height
 * override. Deliberately NO other styling controls — the slider styles itself
 * in the slider editor. At publish time the referenced slider's PUBLISHED
 * block tree is inlined into the page's static output (no runtime lookups).
 * Tracked as an entity_references 'embeds' edge.
 */
class SliderRefBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'slider_ref'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        $dimension = 'regex:/^\d{1,4}(px|vh|%)$/';

        return [
            'sliderId' => ['sometimes', 'nullable', 'uuid'],
            'heightOverride' => ['sometimes', 'nullable', 'array'],
            'heightOverride.desktop' => ['sometimes', 'nullable', 'string', $dimension],
            'heightOverride.tablet' => ['sometimes', 'nullable', 'string', $dimension],
            'heightOverride.mobile' => ['sometimes', 'nullable', 'string', $dimension],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
