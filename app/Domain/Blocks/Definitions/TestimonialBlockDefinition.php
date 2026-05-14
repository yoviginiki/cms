<?php

namespace App\Domain\Blocks\Definitions;

class TestimonialBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'testimonial'; }
    public function category(): string { return 'data'; }

    public function validationRules(): array
    {
        return [
            'items'           => ['sometimes', 'array'],
            'items.*.quote'   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items.*.author'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.role'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.avatar'  => ['sometimes', 'nullable', 'string', 'max:2048'],
            'layout'          => ['sometimes', 'in:single,grid,carousel'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
