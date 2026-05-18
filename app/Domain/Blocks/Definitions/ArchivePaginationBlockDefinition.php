<?php

namespace App\Domain\Blocks\Definitions;

class ArchivePaginationBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'archive-pagination'; }
    public function category(): string { return 'dynamic'; }

    public function validationRules(): array
    {
        return [
            'style' => ['sometimes', 'in:numbered,simple,load-more'],
            'align' => ['sometimes', 'in:left,center,right'],
        ];
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
