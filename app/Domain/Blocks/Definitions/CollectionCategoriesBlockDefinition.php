<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Category list of a collection (frontend: collection-categories). Was the
 * one user-facing block without a server-side definition (audit F31).
 */
class CollectionCategoriesBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'collection-categories'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        $cssDim = 'regex:/^(0|\d+(\.\d+)?(px|rem|em|%|vh|vw))$/';

        return [
            'collectionId' => ['sometimes', 'nullable', 'uuid'],
            'parentNodeId' => ['sometimes', 'nullable', 'uuid'],
            'layout' => ['sometimes', 'in:cards,pills,list'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'showImage' => ['sometimes', 'boolean'],
            'imageHeight' => ['sometimes', 'nullable', 'string', 'max:20', $cssDim],
            'showName' => ['sometimes', 'boolean'],
            'showDescription' => ['sometimes', 'boolean'],
            'showCount' => ['sometimes', 'boolean'],
            'hideEmpty' => ['sometimes', 'boolean'],
            'gap' => ['sometimes', 'nullable', 'string', 'max:20', $cssDim],
        ];
    }

    public function sanitizationConfig(): array { return []; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
