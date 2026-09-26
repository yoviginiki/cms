<?php

namespace App\Domain\Blocks\Definitions;

/** The site's logo and/or name (+ tagline) from Site Settings → Branding, linking home. */
class SiteIdentityBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'site-identity'; }
    public function category(): string { return 'navigation'; }

    public function validationRules(): array
    {
        return [
            'show' => ['sometimes', 'in:auto,logo,name,both'],
            'showTagline' => ['sometimes', 'boolean'],
            'size' => ['sometimes', 'in:sm,md,lg'],
            'linkHome' => ['sometimes', 'boolean'],
            'align' => ['sometimes', 'in:left,center,right'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
