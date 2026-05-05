<?php

namespace App\Services\Theme\Coverage;

class CoverageReport
{
    public array $gaps = [];
    public array $fallbacks = [];

    public function __construct(
        public string $themeId,
        public string $mode,
    ) {}

    public function addGap(string $block, string $tokenPath, Severity $severity, string $purpose = '', ?string $fallback = null): void
    {
        $this->gaps[] = compact('block', 'tokenPath', 'severity', 'purpose', 'fallback');
    }

    public function addFallback(string $block, string $tokenPath, string $fallbackPath): void
    {
        $this->fallbacks[] = compact('block', 'tokenPath', 'fallbackPath');
    }

    public function isPassing(): bool
    {
        return $this->criticalCount() === 0;
    }

    public function criticalCount(): int
    {
        return count(array_filter($this->gaps, fn ($g) => $g['severity'] === Severity::Critical));
    }

    public function warningCount(): int
    {
        return count(array_filter($this->gaps, fn ($g) => $g['severity'] === Severity::Warning));
    }

    public function fallbackCount(): int
    {
        return count($this->fallbacks);
    }

    public function byBlock(string $slug): array
    {
        return array_filter($this->gaps, fn ($g) => $g['block'] === $slug);
    }

    public function toArray(): array
    {
        return [
            'theme_id' => $this->themeId,
            'mode' => $this->mode,
            'passing' => $this->isPassing(),
            'critical' => $this->criticalCount(),
            'warnings' => $this->warningCount(),
            'fallbacks' => $this->fallbackCount(),
            'gaps' => array_map(fn ($g) => [
                ...$g,
                'severity' => $g['severity']->value,
            ], $this->gaps),
            'fallback_list' => $this->fallbacks,
        ];
    }
}
