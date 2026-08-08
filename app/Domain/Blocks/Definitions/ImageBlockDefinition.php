<?php

namespace App\Domain\Blocks\Definitions;

use App\Domain\Projection\Contracts\ProvidesProjection;
use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Descriptors\FieldType;

class ImageBlockDefinition implements BlockDefinition, ProvidesProjection
{
    public function type(): string { return 'image'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'asset_id' => ['sometimes', 'nullable', 'uuid'],
            'assetId' => ['sometimes', 'nullable', 'uuid'],
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

    public function projection(): ?BlockProjection
    {
        // schema.org ImageObject. Both asset_id and assetId casings are
        // declared because the data uses either; the builder emits whichever
        // is present. url/asset feed the asset inventory; alt/caption are
        // searchable text.
        return BlockProjection::make()
            ->schemaType('ImageObject')
            ->field('asset_id', 'image', FieldType::AssetRef, [
                'schema' => true,
                'inventory' => true,
            ])
            ->field('assetId', 'image', FieldType::AssetRef, [
                'schema' => true,
                'inventory' => true,
            ])
            ->field('url', 'contentUrl', FieldType::Url, [
                'schema' => true,
                'inventory' => true,
            ])
            ->field('alt', 'name', FieldType::Text, [
                'schema' => true,
                'rag' => true,
            ])
            ->field('caption', 'caption', FieldType::Text, [
                'schema' => true,
                'rag' => true,
            ]);
    }
}
