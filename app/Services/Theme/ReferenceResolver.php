<?php

namespace App\Services\Theme;

use App\Services\Theme\Exceptions\CircularTokenReferenceException;

/**
 * Resolves {token.path} references in a flattened token map.
 * Detects circular references via topological sort.
 */
class ReferenceResolver
{
    private const REF_PATTERN = '/\{([a-zA-Z0-9._-]+)\}/';

    /**
     * Flatten a nested W3C token document into a flat map of path → value,
     * then resolve all {references} transitively.
     *
     * @return array<string, mixed> Flat map of token paths to resolved values.
     */
    public function flatten(array $document): array
    {
        $flat = [];
        $this->extractTokens($document, '', $flat);
        return $this->resolveAll($flat);
    }

    /**
     * Walk the nested document and extract all tokens (objects with $value) into flat paths.
     */
    private function extractTokens(array $node, string $prefix, array &$flat): void
    {
        foreach ($node as $key => $value) {
            if (str_starts_with($key, '$')) continue; // Skip $metadata, $schema, etc.

            $path = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && isset($value['$value'])) {
                // This is a token — store its $value
                $flat[$path] = $value['$value'];
            } elseif (is_array($value)) {
                // This is a group — recurse
                $this->extractTokens($value, $path, $flat);
            }
        }
    }

    /**
     * Resolve all {ref} chains in the flat map.
     */
    private function resolveAll(array $flat): array
    {
        // Build dependency graph
        $deps = [];
        foreach ($flat as $path => $value) {
            $deps[$path] = $this->extractRefs($value);
        }

        // Topological sort
        $sorted = $this->topologicalSort($deps);

        // Resolve in order
        $resolved = [];
        foreach ($sorted as $path) {
            $value = $flat[$path] ?? null;
            if ($value === null) continue;

            $resolved[$path] = $this->resolveValue($value, $resolved);
        }

        return $resolved;
    }

    /**
     * Extract referenced token paths from a value.
     */
    private function extractRefs(mixed $value): array
    {
        if (!is_string($value)) return [];

        preg_match_all(self::REF_PATTERN, $value, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Resolve a single value, substituting {refs} with already-resolved values.
     */
    private function resolveValue(mixed $value, array $resolved): mixed
    {
        if (!is_string($value)) return $value;

        // If the entire value is a single reference, return the referenced value directly
        // (preserves type — e.g., arrays for fontFamily)
        if (preg_match('/^\{([a-zA-Z0-9._-]+)\}$/', $value, $m)) {
            return $resolved[$m[1]] ?? $value;
        }

        // Otherwise substitute inline
        return preg_replace_callback(self::REF_PATTERN, function ($m) use ($resolved) {
            $ref = $m[1];
            $val = $resolved[$ref] ?? $m[0]; // Keep original if unresolved
            return is_array($val) ? implode(', ', $val) : (string) $val;
        }, $value);
    }

    /**
     * Topological sort with cycle detection.
     *
     * @param array<string, string[]> $deps Map of node → dependencies.
     * @return string[] Sorted node list (dependencies first).
     * @throws CircularTokenReferenceException
     */
    private function topologicalSort(array $deps): array
    {
        $sorted = [];
        $visited = []; // 0 = unvisited, 1 = in-progress, 2 = done
        $stack = [];

        foreach (array_keys($deps) as $node) {
            if (($visited[$node] ?? 0) === 0) {
                $this->dfs($node, $deps, $visited, $sorted, $stack);
            }
        }

        return $sorted;
    }

    private function dfs(string $node, array $deps, array &$visited, array &$sorted, array &$stack): void
    {
        $visited[$node] = 1; // in-progress
        $stack[] = $node;

        foreach ($deps[$node] ?? [] as $dep) {
            if (($visited[$dep] ?? 0) === 1) {
                // Cycle detected — extract the cycle from the stack
                $cycleStart = array_search($dep, $stack);
                $cycle = array_slice($stack, $cycleStart);
                $cycle[] = $dep;
                throw new CircularTokenReferenceException($cycle);
            }

            if (($visited[$dep] ?? 0) === 0) {
                $this->dfs($dep, $deps, $visited, $sorted, $stack);
            }
        }

        array_pop($stack);
        $visited[$node] = 2; // done
        $sorted[] = $node;
    }
}
