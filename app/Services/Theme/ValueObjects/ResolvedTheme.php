<?php

namespace App\Services\Theme\ValueObjects;

class ResolvedTheme
{
    /**
     * @param array<string, mixed> $tokens Flattened token path → resolved value
     */
    public function __construct(
        public array $tokens,
        public string $contentHash,
    ) {}

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->tokens[$path] ?? $default;
    }

    public function has(string $path): bool
    {
        return isset($this->tokens[$path]);
    }

    public function toArray(): array
    {
        return $this->tokens;
    }

    /**
     * Emit CSS custom properties for :root{} injection.
     */
    public function toCssVariables(): string
    {
        $lines = [];
        foreach ($this->tokens as $path => $value) {
            $varName = '--' . str_replace('.', '-', $path);
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $lines[] = "  {$varName}: {$value};";
        }

        return ":root {\n" . implode("\n", $lines) . "\n}";
    }
}
