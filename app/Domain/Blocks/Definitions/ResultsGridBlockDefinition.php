<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G2 search island block: renders results client-side using a card
 * <template> emitted at publish (mustache-grade slots — no framework).
 * Progressive enhancement: with JS off, the static archive listing rendered
 * alongside (record-loop / archive page) remains usable.
 */
class ResultsGridBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'results-grid'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'collectionId' => ['sometimes', 'nullable', 'uuid'],
            'eager' => ['sometimes', 'boolean'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'showImage' => ['sometimes', 'boolean'],
            'cardFields' => ['sometimes', 'array', 'max:6'],
            'cardFields.*' => ['string', 'max:40'],
            'emptyText' => ['sometimes', 'nullable', 'string', 'max:120'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
