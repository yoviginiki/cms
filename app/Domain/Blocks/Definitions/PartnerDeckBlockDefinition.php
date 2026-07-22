<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Interactive prompt-card deck — steps through a configurable set of title/body
 * cards one at a time. Behaviour comes from the self-hosted app-tools runtime
 * (AppToolRender).
 */
class PartnerDeckBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'partner-deck'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'eyebrow'      => ['sometimes', 'nullable', 'string', 'max:80'],
            'buttonLabel'  => ['sometimes', 'nullable', 'string', 'max:40'],
            'cards'        => ['sometimes', 'array', 'max:60'],
            'cards.*.title'=> ['sometimes', 'string', 'max:120'],
            'cards.*.body' => ['sometimes', 'string', 'max:400'],
        ];
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
