<?php

namespace App\Support\Blocks;

/**
 * Shared validation rules for slider LAYER blocks (any block placed inside a
 * slide): scene-based animation (in/loop/out), absolute layout on the slide
 * canvas, and click triggers.
 *
 * Allowlists mirror the reference prototype's SPEC NOTES (§3 presets) and the
 * motion-runtime module — anything outside them never reaches the published
 * JSON blob.
 */
class SliderAnimation
{
    public const PRESETS = [
        'fadeUp', 'fadeUp-out', 'fadeIn', 'fadeOut',
        'slideLeft', 'slideLeft-out', 'slideRight', 'slideRight-out',
        'zoomIn', 'maskWipe',
    ];

    public const ATTRS = ['x', 'y', 'scale', 'rotation', 'autoAlpha', 'opacity', 'clipPath'];

    public const EASE_PATTERN = '/^((power[1-4]|sine|expo|circ)\.(in|out|inOut)|back\.out\(1\.6\)|none|linear)$/';

    public const TRIGGER_ACTIONS = ['link', 'goToSlide'];

    public const SPLIT_MODES = ['none', 'chars', 'words', 'lines'];

    /** x/y accept px or % (e.g. "8%", "120px", "-40px") */
    private const COORD_PATTERN = '/^-?\d{1,4}(\.\d{1,2})?(px|%)$/';

    public static function validationRules(): array
    {
        $presets = implode(',', self::PRESETS);
        $attrs = implode(',', self::ATTRS);

        $sceneRules = function (string $scene) use ($presets, $attrs) {
            return [
                "animation.{$scene}" => ['sometimes', 'nullable', 'array'],
                "animation.{$scene}.preset" => ['sometimes', 'nullable', "in:{$presets}"],
                "animation.{$scene}.delay" => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
                "animation.{$scene}.duration" => ['sometimes', 'nullable', 'numeric', 'min:0.05', 'max:10'],
                "animation.{$scene}.stagger" => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
                "animation.{$scene}.tracks" => ['sometimes', 'array', 'max:8'],
                "animation.{$scene}.tracks.*.attr" => ['required_with:animation.' . $scene . '.tracks', "in:{$attrs}"],
                "animation.{$scene}.tracks.*.from" => ['sometimes', 'nullable', 'string', 'max:60'],
                "animation.{$scene}.tracks.*.to" => ['sometimes', 'nullable', 'string', 'max:60'],
                "animation.{$scene}.tracks.*.delay" => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
                "animation.{$scene}.tracks.*.duration" => ['sometimes', 'nullable', 'numeric', 'min:0.05', 'max:10'],
                "animation.{$scene}.tracks.*.ease" => ['sometimes', 'nullable', 'regex:' . self::EASE_PATTERN],
                "animation.{$scene}.tracks.*.yoyo" => ['sometimes', 'boolean'],
                "animation.{$scene}.tracks.*.repeat" => ['sometimes', 'integer', 'min:-1', 'max:20'],
            ];
        };

        return [
            'animation' => ['sometimes', 'nullable', 'array'],
            'animation.split' => ['sometimes', 'nullable', 'in:' . implode(',', self::SPLIT_MODES)],
            'animation.trigger' => ['sometimes', 'nullable', 'array'],
            'animation.trigger.action' => ['sometimes', 'nullable', 'in:' . implode(',', self::TRIGGER_ACTIONS)],
            'animation.trigger.target' => ['sometimes', 'nullable', 'string', 'max:300', 'not_regex:/^(javascript|data|vbscript):/i'],

            // absolute layout on the slide canvas (matches prototype layer shape)
            'layout' => ['sometimes', 'nullable', 'array'],
            'layout.x' => ['sometimes', 'nullable', 'regex:' . self::COORD_PATTERN],
            'layout.y' => ['sometimes', 'nullable', 'regex:' . self::COORD_PATTERN],
            'layout.widthPct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'layout.heightPct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'layout.rotation' => ['sometimes', 'nullable', 'numeric', 'min:-360', 'max:360'],
            'layout.zIndex' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:99'],

            // per-breakpoint layout overrides (tablet ≤1023px, mobile ≤767px —
            // same breakpoints as BlockStyle's responsive emitters)
            'responsiveLayout' => ['sometimes', 'nullable', 'array'],
            'responsiveLayout.tablet' => ['sometimes', 'nullable', 'array'],
            'responsiveLayout.tablet.x' => ['sometimes', 'nullable', 'regex:' . self::COORD_PATTERN],
            'responsiveLayout.tablet.y' => ['sometimes', 'nullable', 'regex:' . self::COORD_PATTERN],
            'responsiveLayout.tablet.widthPct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'responsiveLayout.tablet.heightPct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'responsiveLayout.tablet.hidden' => ['sometimes', 'boolean'],
            'responsiveLayout.mobile' => ['sometimes', 'nullable', 'array'],
            'responsiveLayout.mobile.x' => ['sometimes', 'nullable', 'regex:' . self::COORD_PATTERN],
            'responsiveLayout.mobile.y' => ['sometimes', 'nullable', 'regex:' . self::COORD_PATTERN],
            'responsiveLayout.mobile.widthPct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'responsiveLayout.mobile.heightPct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'responsiveLayout.mobile.hidden' => ['sometimes', 'boolean'],
        ]
            + $sceneRules('in')
            + $sceneRules('loop')
            + $sceneRules('out');
    }
}
