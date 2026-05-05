<?php

namespace App\Services\Theme;

/**
 * W3C-aware deep merge for token documents.
 * Later layers win. Only merges at the leaf ($value) level.
 */
class TokenMerger
{
    /**
     * Merge multiple token document layers. Later entries override earlier ones.
     *
     * @param array<int, array> $layers Ordered list of token documents (sparse deltas).
     * @return array Merged document.
     */
    public function merge(array $layers): array
    {
        $result = [];

        foreach ($layers as $layer) {
            if (empty($layer)) continue;
            $result = $this->deepMerge($result, $layer);
        }

        return $result;
    }

    private function deepMerge(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            // Skip metadata keys
            if (str_starts_with($key, '$')) {
                $base[$key] = $value;
                continue;
            }

            // If the overlay value is a token (has $value), it replaces entirely
            if (is_array($value) && isset($value['$value'])) {
                $base[$key] = $value;
                continue;
            }

            // If both are arrays (groups), recurse
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
                continue;
            }

            // Otherwise, overlay wins
            $base[$key] = $value;
        }

        return $base;
    }
}
