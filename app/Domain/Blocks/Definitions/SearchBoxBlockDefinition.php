<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G2 search island block: the query input. Static shell +
 * data-attributes; the vanilla-JS island (collections-search.js) binds it.
 * Tier-agnostic by design — the data source (static index vs API) is
 * resolved at publish.
 */
class SearchBoxBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'search-box'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'collectionId' => ['sometimes', 'nullable', 'string', 'max:36', 'regex:/^(\*|[0-9a-fA-F-]{36})$/'], // uuid or '*' (cross-collection, v3)
            'placeholder' => ['sometimes', 'nullable', 'string', 'max:100'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
