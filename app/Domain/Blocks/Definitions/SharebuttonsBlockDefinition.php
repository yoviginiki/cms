<?php

namespace App\Domain\Blocks\Definitions;

class SharebuttonsBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'sharebuttons'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'platforms'   => ['sometimes', 'array'],
            'platforms.*' => ['sometimes', 'in:twitter,facebook,linkedin,email,copy'],
            'style'       => ['sometimes', 'in:icons,buttons,minimal'],
            'showLabels'  => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
