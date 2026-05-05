<?php

namespace App\Services\Theme\Coverage;

class TokenManifest
{
    private array $required = [];
    private array $optional = [];

    public static function make(): self
    {
        return new self();
    }

    public function requires(string $path, string $purpose = ''): self
    {
        $this->required[$path] = $purpose;
        return $this;
    }

    public function optional(string $path, string $fallback, string $purpose = ''): self
    {
        $this->optional[$path] = compact('fallback', 'purpose');
        return $this;
    }

    public function required(): array
    {
        return $this->required;
    }

    public function optionalWithFallbacks(): array
    {
        return $this->optional;
    }

    public function allPaths(): array
    {
        return array_unique(array_merge(
            array_keys($this->required),
            array_keys($this->optional),
        ));
    }
}
