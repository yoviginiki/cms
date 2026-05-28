<?php

namespace App\Domain\Blocks\Definitions;

class AuthorboxBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'authorbox'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'showAvatar'      => ['sometimes', 'boolean'],
            'showBio'         => ['sometimes', 'boolean'],
            'showSocialLinks' => ['sometimes', 'boolean'],
            'layout'          => ['sometimes', 'in:horizontal,vertical'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
