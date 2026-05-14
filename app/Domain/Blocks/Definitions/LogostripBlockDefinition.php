<?php

namespace App\Domain\Blocks\Definitions;

class LogostripBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'logostrip'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            'logos'       => ['sometimes', 'array'],
            'logos.*'     => ['sometimes', 'nullable', 'string', 'max:2048'],
            'grayscale'   => ['sometimes', 'boolean'],
            'columns'     => ['sometimes', 'integer', 'min:1', 'max:8'],
            'gap'         => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
