<?php

namespace App\Services\IssueStudio;

use RuntimeException;

/**
 * Loads the editorial playbook (resources/playbook/*.md) as cacheable
 * system blocks for Opus calls. The playbook is the product's brain —
 * editorial judgment lives in these documents, not in code.
 */
class Playbook
{
    public const GENRES = ['politics', 'art-culture', 'business', 'lifestyle', 'interview'];

    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? resource_path('playbook');
    }

    public function doc(string $name): string
    {
        $path = $this->basePath . '/' . $name . '.md';
        if (!is_file($path)) {
            throw new RuntimeException("Playbook document missing: {$name}.md");
        }

        return (string) file_get_contents($path);
    }

    /**
     * System blocks for a generation call (Opus). Stable content in one
     * block carrying cache_control so the whole playbook prefix is cached;
     * volatile per-call context must be appended by the caller AFTER these.
     *
     * @param string[] $docs e.g. ['universal', 'politics', 'flatplan', 'spread-patterns']
     */
    public function systemBlocks(array $docs): array
    {
        $parts = [];
        foreach ($docs as $name) {
            $parts[] = "<playbook doc=\"{$name}\">\n" . trim($this->doc($name)) . "\n</playbook>";
        }

        return [[
            'type' => 'text',
            'text' => implode("\n\n", $parts),
            'cache_control' => ['type' => 'ephemeral'],
        ]];
    }

    /** Docs for a flatplan/spread call given the brief's genre. */
    public function docsForGenre(?string $genre, array $extra = []): array
    {
        $docs = ['universal'];
        if ($genre && in_array($genre, self::GENRES, true)) {
            $docs[] = $genre;
        }

        return array_merge($docs, $extra);
    }
}
