<?php

namespace App\Domain\Blocks\Definitions;

class MapBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'map'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'lat'         => ['sometimes', 'numeric', 'min:-90', 'max:90'],
            'lng'         => ['sometimes', 'numeric', 'min:-180', 'max:180'],
            'zoom'        => ['sometimes', 'integer', 'min:1', 'max:20'],
            'markerLabel' => ['sometimes', 'nullable', 'string', 'max:255'],
            'height'      => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|vh|%)$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
