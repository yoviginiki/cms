<?php

namespace App\Domain\Blocks\Definitions;

class ImageBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'image'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'asset_id' => ['sometimes', 'nullable', 'uuid'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
            'size' => ['sometimes', 'in:small,medium,large,full'],
            'objectFit' => ['sometimes', 'nullable', 'in:cover,contain,fill,scale-down,none'],
            'objectPosition' => ['sometimes', 'nullable', 'in:top,bottom,left,right,left top,right top,left bottom,right bottom,center'],
            'width' => ['sometimes', 'nullable', 'string', 'max:12', 'regex:/^(auto|\d{1,5}(px|%)?)$/'],
            'height' => ['sometimes', 'nullable', 'string', 'max:12', 'regex:/^(auto|\d{1,5}(px|%)?)$/'],
        ] + \App\Support\Blocks\BlockEffects::validationRules()
          + \App\Support\Blocks\SliderAnimation::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
