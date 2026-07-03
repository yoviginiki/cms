<?php

namespace App\Domain\Blocks\Definitions;

/**
 * One slide inside a slider entity. Children are layer blocks (existing
 * primitives: text/image/button/shape/video/audio/group) carrying
 * SliderAnimation layout + scene data.
 */
class SlideBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'slide'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'background' => ['sometimes', 'array'],
            'background.type' => ['sometimes', 'in:image,video,color'],
            'background.assetId' => ['sometimes', 'nullable', 'uuid'],
            'background.src' => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'background.color' => ['sometimes', 'nullable', 'string', 'max:50'],
            // solid colors and simple gradients only; validated shape, emitted via style attr
            'background.overlay' => ['sometimes', 'nullable', 'string', 'max:300', 'regex:/^(rgba?\([\d\s.,%]+\)|#[0-9a-fA-F]{3,8}|(linear|radial)-gradient\([^;{}<>]{1,250}\))$/'],
            'background.kenBurns' => ['sometimes', 'boolean'],
            'background.parallax' => ['sometimes', 'boolean'],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:1000', 'max:30000'],
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 40; }
}
