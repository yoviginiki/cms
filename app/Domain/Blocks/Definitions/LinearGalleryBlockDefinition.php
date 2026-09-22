<?php

namespace App\Domain\Blocks\Definitions;

/**
 * "Linear gallery": one horizontal, draggable strip of overlapping images of
 * varying size (à la aletagency.com). Hover lifts + outlines an image, click
 * opens the shared lightbox.
 */
class LinearGalleryBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'linear-gallery'; }
    public function category(): string { return 'media'; }

    public const SHADOWS = ['none', 'soft', 'medium', 'strong'];

    public function validationRules(): array
    {
        $color = 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s.,%]+\)|hsla?\([\d\s.,%]+\)|var\(--[\w-]+\)|transparent|[a-zA-Z]+)$/';
        $shadow = 'in:' . implode(',', self::SHADOWS);
        $shadowOrBool = ['sometimes', 'nullable', function (string $attr, mixed $v, \Closure $fail) {
            if (!is_bool($v) && !in_array($v, self::SHADOWS, true)) {
                $fail("{$attr} must be one of none, soft, medium, strong.");
            }
        }];

        return GalleryImageRules::rules('images') + [
            // composition
            'height'           => ['sometimes', 'integer', 'min:80', 'max:1600'],
            'mobileHeight'     => ['sometimes', 'integer', 'min:60', 'max:1000'],
            'stripHeight'      => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2000'],
            'sizeVariation'    => ['sometimes', 'in:none,subtle,strong'],
            'overlap'          => ['sometimes', 'integer', 'min:0', 'max:60'],
            'scatter'          => ['sometimes', 'integer', 'min:0', 'max:60'],
            'align'            => ['sometimes', 'in:start,center'],
            'width'            => ['sometimes', 'in:contained,full'],
            'offsetStart'      => ['sometimes', 'integer', 'min:0', 'max:600'],
            'offsetEnd'        => ['sometimes', 'integer', 'min:0', 'max:600'],
            'padding'          => ['sometimes', 'integer', 'min:0', 'max:300'],
            'radius'           => ['sometimes', 'integer', 'min:0', 'max:60'],
            // look
            'blend'            => ['sometimes', 'in:multiply,screen,darken,luminosity,normal'],
            'opacity'          => ['sometimes', 'integer', 'min:30', 'max:100'],
            'background'       => ['sometimes', 'nullable', 'string', 'max:64', $color],
            'borderColor'      => ['sometimes', 'nullable', 'string', 'max:64', $color],
            'borderWidth'      => ['sometimes', 'integer', 'min:0', 'max:12'],
            'shadow'           => ['sometimes', $shadow],
            // hover
            'hoverBorderColor' => ['sometimes', 'nullable', 'string', 'max:64', $color],
            'hoverBorderWidth' => ['sometimes', 'integer', 'min:0', 'max:12'],
            'hoverGlow'        => ['sometimes', 'boolean'],
            'hoverShadow'      => $shadowOrBool,
            'hoverLift'        => ['sometimes', 'boolean'],
            // controls
            'arrows'           => ['sometimes', 'boolean'],
            'arrowsShow'       => ['sometimes', 'in:hover,always'],
            'arrowsPosition'   => ['sometimes', 'in:sides,bottom-right,bottom-center,top-right'],
            'arrowsSize'       => ['sometimes', 'in:sm,md,lg'],
            'arrowsColor'      => ['sometimes', 'nullable', 'string', 'max:64', $color],
            'arrowsBg'         => ['sometimes', 'nullable', 'string', 'max:64', $color],
            'arrowsMobile'     => ['sometimes', 'boolean'],
            'drag'             => ['sometimes', 'boolean'],
            'scrollbar'        => ['sometimes', 'boolean'],
            'autoplay'         => ['sometimes', 'integer', 'min:0', 'max:200'],
            'lightbox'         => ['sometimes', 'boolean'],
            'openOn'           => ['sometimes', 'in:click,dblclick'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
