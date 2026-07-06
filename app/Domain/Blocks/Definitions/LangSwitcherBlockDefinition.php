<?php

namespace App\Domain\Blocks\Definitions;

class LangSwitcherBlockDefinition implements BlockDefinition
{
    public function type(): string
    {
        return 'langswitcher';
    }

    public function category(): string
    {
        return 'navigation';
    }

    public function validationRules(): array
    {
        return [
            'style' => ['sometimes', 'in:inline,dropdown'],
            'display' => ['sometimes', 'in:code,name,flag,flag-code,flag-name'],
            'flagSize' => ['sometimes', 'integer', 'min:10', 'max:64'],
            'fontSize' => ['sometimes', 'integer', 'min:9', 'max:48'],
            'gap' => ['sometimes', 'integer', 'min:2', 'max:48'],
            'uppercase' => ['sometimes', 'boolean'],
            'separator' => ['sometimes', 'in:slash,pipe,dot,none'],
            'alignment' => ['sometimes', 'in:left,center,right'],
            'textColor' => ['sometimes', 'nullable', 'string', 'max:20'],
            'activeColor' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool
    {
        return false;
    }

    public function maxChildren(): ?int
    {
        return null;
    }
}
