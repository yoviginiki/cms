<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G-Q block: a saved query's grouped/list results as a styled table.
 * Pre-rendered at publish, republished via the staleness cascade.
 */
class QueryTableBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'query-table'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'queryId' => ['sometimes', 'nullable', 'uuid'],
            'showHeader' => ['sometimes', 'boolean'],
            'maxRows' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'striped' => ['sometimes', 'boolean'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
