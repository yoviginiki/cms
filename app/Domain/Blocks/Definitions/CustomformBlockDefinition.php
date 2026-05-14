<?php

namespace App\Domain\Blocks\Definitions;

class CustomformBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'customform'; }
    public function category(): string { return 'forms'; }

    public function validationRules(): array
    {
        return [
            'fields'              => ['sometimes', 'array'],
            'fields.*.type'       => ['sometimes', 'in:text,email,textarea,select,checkbox,radio,file'],
            'fields.*.label'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'fields.*.required'   => ['sometimes', 'boolean'],
            'fields.*.placeholder'=> ['sometimes', 'nullable', 'string', 'max:255'],
            'submitText'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'endpoint'            => ['sometimes', 'nullable', 'string', 'max:2048', 'not_regex:/^(javascript|data|vbscript):/i'],
            'successMessage'      => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
