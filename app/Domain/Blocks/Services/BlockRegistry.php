<?php

namespace App\Domain\Blocks\Services;

use App\Domain\Blocks\Definitions\BlockDefinition;

class BlockRegistry
{
    /** @var array<string, BlockDefinition> */
    private array $blocks = [];

    public function register(BlockDefinition $definition): void
    {
        $this->blocks[$definition->type()] = $definition;
    }

    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    public function get(string $type): ?BlockDefinition
    {
        return $this->blocks[$type] ?? null;
    }

    public function validate(string $type, array $data): bool
    {
        $definition = $this->get($type);

        if (!$definition) {
            return false;
        }

        $validator = validator($data, $definition->validationRules());

        return $validator->passes();
    }

    public function getAllTypes(): array
    {
        $types = [];

        foreach ($this->blocks as $type => $definition) {
            $types[] = [
                'type' => $type,
                'category' => $definition->category(),
                'allows_children' => $definition->allowsChildren(),
                'max_children' => $definition->maxChildren(),
                'validation_rules' => $definition->validationRules(),
            ];
        }

        return $types;
    }

    public function getByCategory(string $category): array
    {
        return array_filter(
            $this->getAllTypes(),
            fn($t) => $t['category'] === $category
        );
    }
}
