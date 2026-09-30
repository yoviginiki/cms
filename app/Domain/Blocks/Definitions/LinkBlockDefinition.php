<?php

namespace App\Domain\Blocks\Definitions;

use App\Support\Blocks\LinkIcons;

class LinkBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'link'; }
    public function category(): string { return 'content'; }

    public function validationRules(): array
    {
        return [
            'text'       => ['sometimes', 'nullable', 'string', 'max:500'],
            'linkType'   => ['sometimes', 'in:page,post,custom'],
            'linkUrl'    => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'linkPageId' => ['sometimes', 'nullable', 'string', 'max:64'],
            'linkPostId' => ['sometimes', 'nullable', 'string', 'max:64'],
            'linkTarget' => ['sometimes', 'in:_self,_blank'],
            'icon'       => ['sometimes', 'in:' . implode(',', array_merge(['auto', 'none'], array_keys(LinkIcons::SHAPES)))],
            'iconPosition' => ['sometimes', 'in:left,right'],
            'brandColor' => ['sometimes', 'boolean'],
            'color'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'fontSize'   => ['sometimes', 'nullable', 'string', 'max:20'],
            'fontWeight' => ['sometimes', 'nullable', 'in:,400,500,600,700,800'],
            'underline'  => ['sometimes', 'in:none,hover,always'],
            'align'      => ['sometimes', 'in:left,center,right'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
