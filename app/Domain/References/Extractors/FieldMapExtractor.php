<?php

namespace App\Domain\References\Extractors;

use App\Domain\References\Contracts\ReferenceExtractor;
use App\Domain\References\ExtractionContext;
use Illuminate\Support\Str;

/**
 * Declarative extractor covering the reference shapes that occur in block data:
 *
 * - idFields:      field => [target_type, kind]; value must be a UUID string
 * - urlFields:     dot-paths (wildcards allowed) of URL values; resolved via
 *                  ExtractionContext (asset serve URLs, internal page/post links).
 *                  Items may be strings or legacy objects with a src/url key.
 * - htmlFields:    dot-paths of HTML strings; href/src attributes are extracted
 *                  and resolved like urlFields
 * - listFallbacks: field => target_type; when the field is EMPTY the block lists
 *                  "any {target_type}" → wildcard lists edge (target_id null)
 * - wildcardLists: target_types that ALWAYS get a wildcard lists edge
 */
class FieldMapExtractor implements ReferenceExtractor
{
    public function __construct(
        private array $idFields = [],
        private array $urlFields = [],
        private array $htmlFields = [],
        private array $listFallbacks = [],
        private array $wildcardLists = [],
    ) {
    }

    public function extract(array $data, ExtractionContext $context): array
    {
        $edges = [];

        foreach ($this->idFields as $field => [$targetType, $kind]) {
            $value = data_get($data, $field); // dot paths allowed (e.g. background.assetId)
            if (is_string($value) && Str::isUuid($value)) {
                $edges[] = ['target_type' => $targetType, 'target_id' => strtolower($value), 'kind' => $kind];
            }
        }

        foreach ($this->urlFields as $path) {
            foreach ($this->values($data, $path) as $value) {
                if ($edge = $context->resolveUrl($value)) {
                    $edges[] = $edge;
                }
            }
        }

        foreach ($this->htmlFields as $path) {
            foreach ($this->values($data, $path) as $html) {
                if (!is_string($html) || $html === '') {
                    continue;
                }
                if (preg_match_all('/\b(?:href|src)\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
                    foreach ($m[1] as $url) {
                        if ($edge = $context->resolveUrl($url)) {
                            $edges[] = $edge;
                        }
                    }
                }
            }
        }

        foreach ($this->listFallbacks as $field => $targetType) {
            if (empty($data[$field])) {
                $edges[] = ['target_type' => $targetType, 'target_id' => null, 'kind' => 'lists'];
            }
        }

        foreach ($this->wildcardLists as $targetType) {
            $edges[] = ['target_type' => $targetType, 'target_id' => null, 'kind' => 'lists'];
        }

        return $edges;
    }

    /**
     * Collect leaf values for a dot-path, tolerating legacy shapes:
     * strings, objects with src/url keys, and one extra level of list nesting.
     */
    private function values(array $data, string $path): array
    {
        $raw = data_get($data, $path);
        if ($raw === null) {
            return [];
        }
        if (!str_contains($path, '*')) {
            $raw = [$raw];
        }

        $values = [];
        foreach ((array) $raw as $item) {
            if (is_string($item)) {
                $values[] = $item;
            } elseif (is_array($item)) {
                if (isset($item['src']) || isset($item['url'])) {
                    $values[] = $item['src'] ?? $item['url'];
                } else {
                    // one extra level of list nesting (e.g. multi-wildcard results)
                    foreach ($item as $sub) {
                        if (is_string($sub)) {
                            $values[] = $sub;
                        } elseif (is_array($sub) && (isset($sub['src']) || isset($sub['url']))) {
                            $values[] = $sub['src'] ?? $sub['url'];
                        }
                    }
                }
            }
        }

        return array_filter($values, fn ($v) => is_string($v) && $v !== '');
    }
}
