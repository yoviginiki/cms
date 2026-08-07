<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Culture module block. A single cultural event within a bulletin-section.
 * All string fields are plain text (HTML stripped by the sanitizer); URLs are
 * validated at render via BlockStyle::safeUrl.
 */
class EventCardBlockDefinition implements BlockDefinition
{
    public function type(): string
    {
        return 'event-card';
    }

    public function category(): string
    {
        return 'content';
    }

    public function validationRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'start_at' => ['sometimes', 'nullable', 'string', 'max:40'],
            'end_at' => ['sometimes', 'nullable', 'string', 'max:40'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'venue' => ['sometimes', 'nullable', 'string', 'max:200'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'ticket_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_free' => ['sometimes', 'boolean'],
            'official_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
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
