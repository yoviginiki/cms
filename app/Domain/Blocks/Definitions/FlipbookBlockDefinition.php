<?php

namespace App\Domain\Blocks\Definitions;

class FlipbookBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'flipbook'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'mode' => ['sometimes', 'in:realistic,minimal'],
            'aspect_ratio' => ['sometimes', 'in:1:1,2:3,3:4,210:297,custom'],
            'custom_width_px' => ['nullable', 'integer', 'min:100', 'max:4000'],
            'custom_height_px' => ['nullable', 'integer', 'min:100', 'max:4000'],
            'flipping_time_ms' => ['sometimes', 'integer', 'min:200', 'max:2000'],
            'show_cover' => ['sometimes', 'boolean'],
            'max_shadow_opacity' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'click_to_flip' => ['sometimes', 'boolean'],
            'swipe_to_flip' => ['sometimes', 'boolean'],
            'start_page' => ['sometimes', 'integer', 'min:0'],
            'show_nav_bar' => ['sometimes', 'boolean'],
            'show_fullscreen' => ['sometimes', 'boolean'],
            'show_page_indicator' => ['sometimes', 'boolean'],
            'source' => ['sometimes', 'in:children,pdf,category'],
            'pdf_asset_id' => ['nullable', 'string'],
            'pdf_url' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['nullable', 'string'],
            'posts_order' => ['sometimes', 'in:date_desc,date_asc,title_asc,title_desc'],
            'posts_limit' => ['sometimes', 'integer', 'min:2', 'max:200'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 200; }
}
