<?php

namespace App\Domain\Blocks\Definitions;

interface BlockDefinition
{
    public function type(): string;

    public function category(): string;

    public function validationRules(): array;

    public function sanitizationConfig(): array;

    public function allowsChildren(): bool;

    public function maxChildren(): ?int;
}
