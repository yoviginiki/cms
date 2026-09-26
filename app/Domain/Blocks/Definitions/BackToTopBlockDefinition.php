<?php

namespace App\Domain\Blocks\Definitions;

/** Link/button that scrolls back to the top of the page. */
class BackToTopBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'back-to-top'; }
    public function category(): string { return 'navigation'; }

    public function validationRules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'style' => ['sometimes', 'in:link,button,icon'],
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
