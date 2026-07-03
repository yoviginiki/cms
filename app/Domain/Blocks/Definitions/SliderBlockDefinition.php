<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Root block of a slider LIBRARY entity (blockable_type 'slider').
 * Children are 'slide' blocks. Never placed directly on a page — pages embed
 * sliders via the lightweight slider_ref block.
 */
class SliderBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'slider'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        $dimension = 'regex:/^\d{1,4}(px|vh|%)$/';

        return [
            'height' => ['sometimes', 'array'],
            'height.desktop' => ['sometimes', 'nullable', 'string', $dimension],
            'height.tablet' => ['sometimes', 'nullable', 'string', $dimension],
            'height.mobile' => ['sometimes', 'nullable', 'string', $dimension],
            'swiper' => ['sometimes', 'array'],
            'swiper.effect' => ['sometimes', 'in:slide,fade'],
            'swiper.speed' => ['sometimes', 'integer', 'min:100', 'max:3000'],
            'swiper.loop' => ['sometimes', 'boolean'],
            'swiper.autoplay' => ['sometimes', 'boolean'],
            'swiper.autoplayDelay' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            'swiper.navigation' => ['sometimes', 'boolean'],
            'swiper.pagination' => ['sometimes', 'boolean'],
            'swiper.keyboard' => ['sometimes', 'boolean'],
            'swiper.pauseOnHover' => ['sometimes', 'boolean'],
            'swiper.initialSlide' => ['sometimes', 'integer', 'min:0', 'max:29'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 30; }
}
