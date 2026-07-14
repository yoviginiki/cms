<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G-Q block: one aggregated number from a saved query ("142 parts in
 * stock"). Pre-rendered at publish; staleness cascades through the query's
 * collection edges, so record changes republish the page with fresh numbers.
 */
class QueryStatBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'query-stat'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'queryId' => ['sometimes', 'nullable', 'uuid'],
            'label' => ['sometimes', 'nullable', 'string', 'max:80'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:10'],
            'suffix' => ['sometimes', 'nullable', 'string', 'max:20'],
            'size' => ['sometimes', 'in:sm,md,lg,xl'],
            'textAlign' => ['sometimes', 'nullable', 'in:,left,center,right'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
