<?php

namespace App\Domain\Blocks\Definitions;

class LatestpostsBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'latestposts'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'categoryId'   => ['sometimes', 'nullable', 'string', 'max:36'],
            'limit'        => ['sometimes', 'integer', 'min:1', 'max:50'],
            'columns'      => ['sometimes', 'integer', 'min:1', 'max:6'],
            'layout'       => ['sometimes', 'in:compact,list,cards,featured'],
            'orderBy'      => ['sometimes', 'in:latest,oldest,title,random'],
            'showImage'    => ['sometimes', 'boolean'],
            'showExcerpt'  => ['sometimes', 'boolean'],
            'showDate'     => ['sometimes', 'boolean'],
            'showCategory' => ['sometimes', 'boolean'],
            'showContent'  => ['sometimes', 'boolean'],
            'excerptLength'=> ['sometimes', 'integer', 'min:0', 'max:500'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
