<?php

namespace App\Domain\Blocks\Definitions;

class RunningtextBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'runningtext'; }
    public function category(): string { return 'typography'; }

    public function validationRules(): array
    {
        return [
            'content'    => ['sometimes', 'string'],
            'columns'    => ['sometimes', 'integer', 'min:1', 'max:4'],
            'columnGap'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'columnRule'  => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return [
            'HTML.Allowed' => 'p,br,strong,em,u,s,a[href|target|rel],span[style],sub,sup,h2,h3,h4,blockquote',
        ];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
