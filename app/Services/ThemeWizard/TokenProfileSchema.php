<?php

namespace App\Services\ThemeWizard;

/**
 * The Theme Wizard's intermediate "design read" — a compact, high-level
 * profile the model produces (from a reference image or a conversation) that
 * captures a *feel*, not a copy. It describes type by CHARACTER (so we
 * substitute open fonts) and reduces spacing/radius/shadow to a few honest
 * choices. TokenProfileCompiler expands it into a full T1 theme.json document.
 *
 * schema() is the json_schema handed to the model's structured-output config;
 * TokenProfileValidator enforces the semantic rules a schema can't (contrast,
 * distinctness). Deliberately small — a wide surface makes the model wander.
 */
class TokenProfileSchema
{
    public const LAYOUTS = ['cinematic', 'magazine', 'business', 'portfolio', 'lifestyle', 'standard'];
    public const SCALES = ['compact', 'balanced', 'dramatic'];
    public const DENSITIES = ['tight', 'balanced', 'airy'];
    public const RADII = ['sharp', 'soft', 'rounded'];
    public const SHADOWS = ['none', 'subtle', 'soft'];

    /** @return array the JSON Schema for output_config.format */
    public static function schema(): array
    {
        $hex = ['type' => 'string', 'pattern' => '^#[0-9a-fA-F]{6}$'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'design_read', 'palette', 'typography', 'spacing', 'radius', 'shadow', 'layout'],
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 40],
                'design_read' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 400,
                    'description' => 'Two or three sentences on the feel — what makes it distinct.'],
                'palette' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['brand', 'accent', 'background', 'surface', 'text', 'heading', 'muted', 'border'],
                    'properties' => [
                        'brand'      => $hex + ['description' => 'Primary brand / link color.'],
                        'accent'     => $hex + ['description' => 'Secondary accent, distinct from brand.'],
                        'background' => $hex + ['description' => 'Page canvas.'],
                        'surface'    => $hex + ['description' => 'Cards / raised panels; close to background.'],
                        'text'       => $hex + ['description' => 'Body text; must read on the background.'],
                        'heading'    => $hex + ['description' => 'Headings; usually darker/stronger than body.'],
                        'muted'      => $hex + ['description' => 'Muted/secondary text.'],
                        'border'     => $hex + ['description' => 'Hairlines / dividers.'],
                    ],
                ],
                'typography' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['display_character', 'body_character', 'scale', 'heading_weight'],
                    'properties' => [
                        'display_character' => ['type' => 'string', 'maxLength' => 80,
                            'description' => 'Character of the heading face, e.g. "high-contrast serif", "condensed grotesque". NOT a font name.'],
                        'body_character' => ['type' => 'string', 'maxLength' => 80,
                            'description' => 'Character of the body face, e.g. "neutral geometric sans", "warm humanist sans".'],
                        'display_family' => ['type' => 'string', 'maxLength' => 60,
                            'description' => 'EXACT heading font family name if identifiable (e.g. "Spectral"). Used verbatim when freely available on Google Fonts; otherwise the character guides an open substitute.'],
                        'body_family' => ['type' => 'string', 'maxLength' => 60,
                            'description' => 'EXACT body font family name if identifiable.'],
                        'scale' => ['type' => 'string', 'enum' => self::SCALES,
                            'description' => 'Type-scale drama: compact, balanced, or dramatic (big display).'],
                        // NOTE: Anthropic structured output rejects integer
                        // minimum/maximum; the 300–900 range is enforced by
                        // TokenProfileValidator instead.
                        'heading_weight' => ['type' => 'integer', 'description' => 'Heading font weight, 300–900.'],
                    ],
                ],
                'spacing' => ['type' => 'string', 'enum' => self::DENSITIES, 'description' => 'Whitespace density.'],
                'radius' => ['type' => 'string', 'enum' => self::RADII, 'description' => 'Corner character: sharp (0), soft, or rounded.'],
                'shadow' => ['type' => 'string', 'enum' => self::SHADOWS, 'description' => 'Elevation: none, subtle, or soft.'],
                'layout' => ['type' => 'string', 'enum' => self::LAYOUTS,
                    'description' => 'Overall layout personality that best matches the reference.'],
            ],
        ];
    }
}
