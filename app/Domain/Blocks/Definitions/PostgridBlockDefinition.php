<?php

namespace App\Domain\Blocks\Definitions;

class PostgridBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'postgrid'; }
    public function category(): string { return 'blog'; }

    public function validationRules(): array
    {
        return [
            'categoryId'     => ['sometimes', 'nullable', 'string', 'max:36'],
            'limit'          => ['sometimes', 'integer', 'min:1', 'max:50'],
            'columns'        => ['sometimes', 'integer', 'min:1', 'max:6'],
            'cardStyle'      => ['sometimes', 'in:vertical,horizontal'],
            'gap'            => ['sometimes', 'integer', 'min:0', 'max:64'],
            // Card border
            'cardBorder'       => ['sometimes', 'boolean'],
            'cardBorderWidth'  => ['sometimes', 'integer', 'min:0', 'max:8'],
            'cardBorderColor'  => ['sometimes', 'string', 'max:20'],
            'cardBorderStyle'  => ['sometimes', 'in:solid,dashed,dotted,double,none'],
            'cardBorderRadius' => ['sometimes', 'integer', 'min:0', 'max:32'],
            'cardShadow'       => ['sometimes', 'in:none,sm,md,lg,xl'],
            'cardBg'           => ['sometimes', 'nullable', 'string', 'max:20'],
            'cardPadding'      => ['sometimes', 'string', 'max:50'],
            // Image
            'showImage'      => ['sometimes', 'boolean'],
            'imageHeight'    => ['sometimes', 'integer', 'min:40', 'max:600'],
            'imageWidth'     => ['sometimes', 'string', 'regex:/^(auto|\d{1,3}%)$/'],
            // Heading
            'showHeading'    => ['sometimes', 'boolean'],
            'headingTag'     => ['sometimes', 'in:h2,h3,h4'],
            'headingSize'    => ['sometimes', 'integer', 'min:10', 'max:48'],
            'headingFont'    => ['sometimes', 'string', 'max:100'],
            'headingAlign'   => ['sometimes', 'in:left,center,right'],
            'headingPadding' => ['sometimes', 'string', 'max:50'],
            'headingMargin'  => ['sometimes', 'string', 'max:50'],
            // Excerpt
            'showExcerpt'    => ['sometimes', 'boolean'],
            'excerptLength'  => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'excerptSize'    => ['sometimes', 'integer', 'min:10', 'max:32'],
            'excerptFont'    => ['sometimes', 'string', 'max:100'],
            'excerptAlign'   => ['sometimes', 'in:left,center,right'],
            'excerptPadding' => ['sometimes', 'string', 'max:50'],
            'excerptMargin'  => ['sometimes', 'string', 'max:50'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
