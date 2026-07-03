<?php

namespace App\Domain\Blocks\Definitions;

use App\Support\Blocks\SliderAnimation;

/**
 * Simple rectangle/bar primitive (per the reference prototype's shape layer).
 * Sized/positioned by SliderAnimation layout when used inside a slide.
 */
class ShapeBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'shape'; }
    public function category(): string { return 'design'; }

    public function validationRules(): array
    {
        return [
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
        ] + SliderAnimation::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
