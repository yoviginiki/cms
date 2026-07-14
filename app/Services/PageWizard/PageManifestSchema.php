<?php

namespace App\Services\PageWizard;

/**
 * The Page Wizard's block-manifest — a curated, AI-friendly vocabulary (NOT
 * the full 100-block registry). A flat ordered list of blocks with one
 * structured `columns` kind; PageManifestCompiler turns it into a real
 * section→row→column→module tree. Deliberately small so the model produces
 * reliable, valid output.
 *
 * Anthropic structured-output gotcha (same as TokenProfileSchema): integer
 * minimum/maximum aren't allowed in the enforced schema — ranges/required-
 * per-kind live in PageManifestValidator.
 */
class PageManifestSchema
{
    public const KINDS = ['hero', 'heading', 'text', 'image', 'gallery', 'button', 'cta', 'columns', 'divider', 'spacer'];

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['page_title', 'design_read', 'blocks'],
            'properties' => [
                'page_title' => ['type' => 'string', 'description' => 'A short page title.'],
                'design_read' => ['type' => 'string', 'description' => '2–3 sentences describing the page you built and its structure.'],
                'blocks' => [
                    'type' => 'array',
                    'description' => 'Ordered blocks, top to bottom.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['kind'],
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => self::KINDS],
                            'title' => ['type' => 'string', 'description' => 'hero/cta headline'],
                            'subtitle' => ['type' => 'string', 'description' => 'hero subtitle'],
                            'text' => ['type' => 'string', 'description' => 'heading text or button label'],
                            'body' => ['type' => 'string', 'description' => 'paragraph/cta body copy'],
                            'level' => ['type' => 'string', 'enum' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']],
                            'url' => ['type' => 'string', 'description' => 'image src or button/cta link (http(s))'],
                            'alt' => ['type' => 'string'],
                            'images' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'gallery image urls'],
                            'cta_text' => ['type' => 'string', 'description' => 'hero/cta button label'],
                            'cta_url' => ['type' => 'string'],
                            'align' => ['type' => 'string', 'enum' => ['left', 'center', 'right']],
                            'columns' => [
                                'type' => 'array',
                                'description' => 'for kind=columns: 2–3 side-by-side cells',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'heading' => ['type' => 'string'],
                                        'body' => ['type' => 'string'],
                                        'image' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
