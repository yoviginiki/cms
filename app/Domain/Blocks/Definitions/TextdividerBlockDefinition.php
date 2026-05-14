<?php

namespace App\Domain\Blocks\Definitions;

class TextdividerBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'textdivider'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'style'        => ['sometimes', 'in:line,dots,asterisks,dinkus,custom'],
            'customSymbol' => ['sometimes', 'nullable', 'string', 'max:10'],
            'width'        => ['sometimes', 'in:full,half,quarter'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
