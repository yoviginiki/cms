<?php

namespace App\Domain\Blocks\Definitions;

class PostLoopBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'post-loop'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'layout' => ['sometimes', 'in:cards,list,grid,featured'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'showImage' => ['sometimes', 'boolean'],
            'showExcerpt' => ['sometimes', 'boolean'],
            'showDate' => ['sometimes', 'boolean'],
            'showAuthor' => ['sometimes', 'boolean'],
            'showCategory' => ['sometimes', 'boolean'],
            'imageAspectRatio' => ['sometimes', 'in:16:9,4:3,1:1,3:2'],
            'excerptLines' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'gap' => ['sometimes', 'nullable', 'string', 'max:20'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
