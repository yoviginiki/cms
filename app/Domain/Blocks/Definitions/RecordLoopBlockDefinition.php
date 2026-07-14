<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Track G2 — the generalized post-loop: lists published records of any
 * collection, usable on ANY page ("new arrivals" on a homepage). Inside a
 * record-archive template it consumes the paginated $__archiveRecords
 * context instead of querying, so static pagination works.
 */
class RecordLoopBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'record-loop'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'collectionId' => ['sometimes', 'nullable', 'uuid'],
            'layout' => ['sometimes', 'in:cards,list,grid'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sortField' => ['sometimes', 'nullable', 'string', 'max:40'],
            'sortDirection' => ['sometimes', 'in:asc,desc'],
            'filterField' => ['sometimes', 'nullable', 'string', 'max:40'],
            'filterValue' => ['sometimes', 'nullable', 'string', 'max:120'],
            'showImage' => ['sometimes', 'boolean'],
            'imageField' => ['sometimes', 'nullable', 'string', 'max:40'],
            'cardFields' => ['sometimes', 'array', 'max:6'],
            'cardFields.*' => ['string', 'max:40'],
            'linkToRecord' => ['sometimes', 'boolean'],
            'gap' => ['sometimes', 'nullable', 'string', 'max:20'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
