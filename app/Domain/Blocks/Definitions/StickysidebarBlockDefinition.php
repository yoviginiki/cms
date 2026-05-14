<?php

namespace App\Domain\Blocks\Definitions;

class StickysidebarBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'stickysidebar'; }
    public function category(): string { return 'layout'; }

    public function validationRules(): array
    {
        return [
            'sidebarSide'   => ['sometimes', 'in:left,right'],
            'sidebarWidth'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'gap'           => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
            'stickyOffset'  => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+)?(px|rem|em|%)$/'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return true; }
    public function maxChildren(): ?int { return 20; }
}
