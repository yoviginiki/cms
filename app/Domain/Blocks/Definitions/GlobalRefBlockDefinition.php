<?php

namespace App\Domain\Blocks\Definitions;

/**
 * The page-side embed of a Global Section (Builder Experience P2): just a picker
 * (sectionId). Deliberately NO styling controls — the section styles itself in
 * its own editor. At publish time the referenced section's PUBLISHED block tree
 * is inlined into the page's static output (flat HTML, no runtime lookups).
 * Tracked as an entity_references 'embeds' edge → editing the section cascades
 * staleness to every embedding page. Mirrors slider_ref.
 */
class GlobalRefBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'global_ref'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'sectionId' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
