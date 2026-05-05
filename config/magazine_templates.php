<?php

/**
 * Magazine page templates — the fixed set of layouts the AI can compose with.
 * Each template defines slots that content fills.
 * The AI picks templates and assigns content to slots — never generates coordinates.
 */
return [
    'cover_title_only' => [
        'label' => 'Cover — title only',
        'density' => 'break',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => true],
            'subtitle' => ['type' => 'string_short', 'required' => false],
        ],
    ],
    'cover_image_title' => [
        'label' => 'Cover — image with title',
        'density' => 'visual',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => true],
            'subtitle' => ['type' => 'string_short', 'required' => false],
            'image_1' => ['type' => 'image', 'required' => true],
        ],
    ],
    'chapter_opener_full_bleed' => [
        'label' => 'Chapter opener — full bleed',
        'density' => 'visual',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => true],
            'subtitle' => ['type' => 'string_short', 'required' => false],
            'image_1' => ['type' => 'image', 'required' => true],
        ],
    ],
    'chapter_opener_quiet' => [
        'label' => 'Chapter opener — quiet',
        'density' => 'break',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => true],
            'subtitle' => ['type' => 'string_short', 'required' => false],
            'intro' => ['type' => 'string_long', 'required' => false],
        ],
    ],
    'text_one_column' => [
        'label' => 'Text — single column',
        'density' => 'text_heavy',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => false],
            'body' => ['type' => 'string_long', 'required' => true],
        ],
    ],
    'text_two_column' => [
        'label' => 'Text — two columns',
        'density' => 'text_heavy',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => false],
            'body' => ['type' => 'string_long', 'required' => true],
        ],
    ],
    'text_with_side_image' => [
        'label' => 'Text with side image',
        'density' => 'text_heavy',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => false],
            'body' => ['type' => 'string_long', 'required' => true],
            'image_1' => ['type' => 'image', 'required' => true],
            'caption_1' => ['type' => 'caption', 'required' => false],
        ],
    ],
    'text_with_top_image' => [
        'label' => 'Text with top image',
        'density' => 'text_heavy',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => false],
            'image_1' => ['type' => 'image', 'required' => true],
            'caption_1' => ['type' => 'caption', 'required' => false],
            'body' => ['type' => 'string_long', 'required' => true],
        ],
    ],
    'full_bleed_image' => [
        'label' => 'Full-bleed image',
        'density' => 'visual',
        'aspect' => 'either',
        'slots' => [
            'image_1' => ['type' => 'image', 'required' => true],
        ],
    ],
    'full_bleed_image_caption' => [
        'label' => 'Full-bleed image with caption',
        'density' => 'visual',
        'aspect' => 'either',
        'slots' => [
            'image_1' => ['type' => 'image', 'required' => true],
            'caption_1' => ['type' => 'caption', 'required' => true],
        ],
    ],
    'pullquote_full_page' => [
        'label' => 'Pull quote — full page',
        'density' => 'reflection',
        'aspect' => 'portrait',
        'slots' => [
            'pullquote' => ['type' => 'pullquote', 'required' => true],
            'attribution' => ['type' => 'string_short', 'required' => false],
        ],
    ],
    'pullquote_with_text' => [
        'label' => 'Pull quote with text',
        'density' => 'text_heavy',
        'aspect' => 'portrait',
        'slots' => [
            'pullquote' => ['type' => 'pullquote', 'required' => true],
            'body' => ['type' => 'string_long', 'required' => true],
        ],
    ],
    'grid_2x2_images' => [
        'label' => 'Image grid — 2×2',
        'density' => 'visual',
        'aspect' => 'either',
        'slots' => [
            'image_1' => ['type' => 'image', 'required' => true],
            'image_2' => ['type' => 'image', 'required' => true],
            'image_3' => ['type' => 'image', 'required' => true],
            'image_4' => ['type' => 'image', 'required' => true],
            'caption_1' => ['type' => 'caption', 'required' => false],
        ],
    ],
    'grid_3_images_horizontal' => [
        'label' => 'Image strip — 3 horizontal',
        'density' => 'visual',
        'aspect' => 'landscape',
        'slots' => [
            'image_1' => ['type' => 'image', 'required' => true],
            'image_2' => ['type' => 'image', 'required' => true],
            'image_3' => ['type' => 'image', 'required' => true],
        ],
    ],
    'interview_qa' => [
        'label' => 'Interview Q&A',
        'density' => 'text_heavy',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => true],
            'intro' => ['type' => 'string_long', 'required' => false],
            'body' => ['type' => 'string_long', 'required' => true],
            'image_1' => ['type' => 'image', 'required' => false],
        ],
    ],
    'visual_break_white' => [
        'label' => 'Visual break — white space',
        'density' => 'break',
        'aspect' => 'portrait',
        'slots' => [],
    ],
    'visual_break_texture' => [
        'label' => 'Visual break — texture',
        'density' => 'break',
        'aspect' => 'portrait',
        'slots' => [
            'image_1' => ['type' => 'image', 'required' => false],
        ],
    ],
    'closing_page' => [
        'label' => 'Closing page',
        'density' => 'reflection',
        'aspect' => 'portrait',
        'slots' => [
            'title' => ['type' => 'string_short', 'required' => false],
            'body' => ['type' => 'string_long', 'required' => false],
            'pullquote' => ['type' => 'pullquote', 'required' => false],
        ],
    ],
];
