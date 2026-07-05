<?php

namespace App\Services\IssueStudio;

/**
 * The contract between editorial language and Magazine editor block data:
 * which element types the generator may emit, their JSON schema (forced via
 * structured outputs), and the layout brief Opus receives. Only publish-safe
 * blocks (see spread-patterns.md) are allowed here.
 */
class SpreadElementContract
{
    /** magType => DTP frame_type */
    public const TYPE_MAP = [
        'text_frame' => 'text',
        'headline_frame' => 'text',
        'caption_frame' => 'text',
        'marginalia_frame' => 'text',
        'pullquote_frame' => 'quote',
        'image_frame' => 'image',
        'fullbleed_image' => 'image',
        'background_image' => 'image',
        'circular_image' => 'image',
        'rectangle' => 'shape',
        'ellipse' => 'shape',
        'gradient_overlay' => 'shape',
        'line' => 'line',
        'decorative_rule' => 'decorative',
        'table_frame' => 'text', // driven by metadata._magType
    ];

    public const TEXT_TYPES = ['text_frame', 'headline_frame', 'caption_frame', 'marginalia_frame', 'pullquote_frame'];
    public const IMAGE_TYPES = ['image_frame', 'fullbleed_image', 'background_image', 'circular_image'];
    public const SHAPE_TYPES = ['rectangle', 'ellipse', 'gradient_overlay'];

    /** Static layout brief — part of the cacheable system prefix. */
    public static function layoutBrief(): string
    {
        return <<<'BRIEF'
LAYOUT CONTRACT (how your JSON becomes a real magazine document):

- Coordinates are PER PAGE in points. Page = 595 wide x 842 high (A4 portrait).
  Margins 36pt all round -> live area x 36..559, y 36..806.
- Grid: 12 columns, column width 32.6pt, gutter 12pt. Column i (0-based) starts at
  x = 36 + i * 44.6. Span c columns = width c*44.6 - 12. Respect the grid.
- Images may bleed to the page edges (x/y 0, width to 595, height to 842).
  Text NEVER bleeds - keep text inside the live area.
- A cover is ONE page with side "single". A spread is TWO pages: side "left" then
  side "right". Both pages are seen together - design them as one canvas, but give
  every element coordinates local to ITS page.
- z: higher = on top. Background images/tints lowest (0), text above (10+).
- Fonts: display = "Barlow Condensed", body = "Barlow". Body 9.5-10.5pt on
  line_height 1.4; standfirsts 13-15pt; captions 7.5-8.5pt; headlines 40-72pt;
  pull quotes 18-26pt. One display + one text face - no others.

TEXT FITTING (hard rule - overset text is a defect):
- Body text at 10pt fits roughly width x height / 72 characters per frame.
- Headlines: one line fits roughly width / (font_size * 0.48) characters.
- You are the editor: CUT text to fit. Excerpt the strongest passage of a long
  material rather than shrinking type. Never place more characters than the budget.

ELEMENT TYPES (emit nothing else):
- text_frame: html body copy (<p>, <h2>, <h3>, <b>, <i>, <ul>/<li>, <blockquote>).
  Optional columns (1-3) inside the frame.
- headline_frame: one <p> line (or two short lines) of display text.
- caption_frame: small factual caption, one <p>.
- marginalia_frame: short side note in the outer margin zone.
- pullquote_frame: the quote html + attribution (may be empty string).
- image_frame / fullbleed_image / background_image / circular_image: set
  material_id to an IMAGE material from the inventory. Never invent images.
  focal_x/focal_y (0-1) place the subject. alt is required, factual.
- rectangle / ellipse / gradient_overlay: fill_color hex + opacity (0-100).
  Use for grounds, tint panels and legibility scrims only.
- line / decorative_rule: thin rules; give the rect they occupy.
- table_frame: table_headers (strings) + table_rows (array of string rows).
  Plain text cells. Always include a source line as a caption_frame beneath.

OUTPUT: {"editorial_note": "...", "pages": [{"side": "...", "elements": [...]}]}
The editorial_note is 1-2 sentences to the user explaining your key choices
("Full-bleed opener because this is the strongest image; text held to one column
for restraint"). Write it warm and plain - the user is not an editor.
BRIEF;
    }

    /** JSON schema for structured output. All fields required, nullable where optional. */
    public static function schema(): array
    {
        $nullNum = ['type' => ['number', 'null']];
        $nullStr = ['type' => ['string', 'null']];
        $nullInt = ['type' => ['integer', 'null']];

        $element = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'type', 'x', 'y', 'w', 'h', 'rotation', 'z',
                'html', 'attribution', 'columns',
                'font_size', 'line_height', 'font_family', 'font_weight', 'text_color', 'text_align',
                'material_id', 'alt', 'caption', 'fit_mode', 'focal_x', 'focal_y',
                'fill_color', 'opacity', 'table_headers', 'table_rows',
            ],
            'properties' => [
                'type' => ['type' => 'string', 'enum' => array_keys(self::TYPE_MAP)],
                'x' => ['type' => 'number'],
                'y' => ['type' => 'number'],
                'w' => ['type' => 'number'],
                'h' => ['type' => 'number'],
                'rotation' => $nullNum,
                'z' => $nullInt,
                'html' => $nullStr,
                'attribution' => $nullStr,
                'columns' => $nullInt,
                'font_size' => $nullNum,
                'line_height' => $nullNum,
                'font_family' => $nullStr,
                'font_weight' => $nullStr,
                'text_color' => $nullStr,
                'text_align' => ['anyOf' => [
                    ['type' => 'string', 'enum' => ['left', 'center', 'right', 'justify']],
                    ['type' => 'null'],
                ]],
                'material_id' => $nullStr,
                'alt' => $nullStr,
                'caption' => $nullStr,
                'fit_mode' => ['anyOf' => [
                    ['type' => 'string', 'enum' => ['fill', 'fit', 'stretch', 'original']],
                    ['type' => 'null'],
                ]],
                'focal_x' => $nullNum,
                'focal_y' => $nullNum,
                'fill_color' => $nullStr,
                'opacity' => $nullNum,
                'table_headers' => ['anyOf' => [
                    ['type' => 'array', 'items' => ['type' => 'string']],
                    ['type' => 'null'],
                ]],
                'table_rows' => ['anyOf' => [
                    ['type' => 'array', 'items' => ['type' => 'array', 'items' => ['type' => 'string']]],
                    ['type' => 'null'],
                ]],
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['editorial_note', 'pages'],
            'properties' => [
                'editorial_note' => ['type' => 'string'],
                'pages' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['side', 'elements'],
                        'properties' => [
                            'side' => ['type' => 'string', 'enum' => ['single', 'left', 'right']],
                            'elements' => ['type' => 'array', 'items' => $element],
                        ],
                    ],
                ],
            ],
        ];
    }
}
