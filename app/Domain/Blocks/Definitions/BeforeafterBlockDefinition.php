<?php

namespace App\Domain\Blocks\Definitions;

class BeforeafterBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'beforeafter'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'beforeSrc'       => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'afterSrc'        => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'beforeLabel'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'afterLabel'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'initialPosition' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ] + \App\Support\Blocks\BlockEffects::validationRules();
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
