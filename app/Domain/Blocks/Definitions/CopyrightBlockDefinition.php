<?php

namespace App\Domain\Blocks\Definitions;

/** Copyright line with {year}/{site}/{start} tokens; the year is filled in at publish. */
class CopyrightBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'copyright'; }
    public function category(): string { return 'navigation'; }

    public function validationRules(): array
    {
        return [
            'text' => ['sometimes', 'nullable', 'string', 'max:300'],
            'startYear' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'align' => ['sometimes', 'in:left,center,right'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
