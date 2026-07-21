<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G2 search island block: facet checkboxes/dropdowns for the
 * collection's facetable fields, with live counts. Multi-facet AND logic
 * lives in the island JS; the Blade emits the static facet shell from the
 * schema so the page is meaningful before JS loads.
 */
class FacetFilterBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'facet-filter'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'collectionId' => ['sometimes', 'nullable', 'string', 'max:36', 'regex:/^(\*|[0-9a-fA-F-]{36})$/'], // uuid or '*' (cross-collection, v3)
            'fields' => ['sometimes', 'array', 'max:8'],
            'fields.*' => ['string', 'max:40'],
            'style' => ['sometimes', 'in:checkbox,dropdown'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
