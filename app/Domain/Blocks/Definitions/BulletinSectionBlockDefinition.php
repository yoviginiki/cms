<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Culture module block. A titled grouping of event cards within a bulletin.
 * Children are event-card blocks (the registry does not enforce child type;
 * the receiving endpoint only ships these two together).
 */
class BulletinSectionBlockDefinition implements BlockDefinition
{
    public function type(): string
    {
        return 'bulletin-section';
    }

    public function category(): string
    {
        return 'content';
    }

    public function validationRules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    public function sanitizationConfig(): array
    {
        // Plain-text only — the title is escaped and carries no HTML.
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool
    {
        return true;
    }

    public function maxChildren(): ?int
    {
        return 100;
    }
}
