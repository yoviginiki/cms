<?php

namespace App\Services\Theme\Studio;

class FrameRegistry
{
    public function all(): array
    {
        return [
            (object) ['slug' => 'showcase', 'title' => 'Full Preview', 'description' => 'A complete sample page — the whole theme at a glance'],
            (object) ['slug' => 'hero', 'title' => 'Hero Sections', 'description' => 'Hero blocks in all variants'],
            (object) ['slug' => 'cards', 'title' => 'Card Grid', 'description' => 'Cards with mixed variants'],
            (object) ['slug' => 'typography', 'title' => 'Typography', 'description' => 'Headings, paragraphs, lists, quotes'],
            (object) ['slug' => 'forms', 'title' => 'Forms & Inputs', 'description' => 'Form elements, buttons, inputs'],
            (object) ['slug' => 'navigation', 'title' => 'Navigation', 'description' => 'Headers, footers, menus'],
            (object) ['slug' => 'content', 'title' => 'Blog Content', 'description' => 'Full article layout with blocks'],
        ];
    }

    public function find(string $slug): ?object
    {
        foreach ($this->all() as $frame) {
            if ($frame->slug === $slug) return $frame;
        }
        return null;
    }
}
