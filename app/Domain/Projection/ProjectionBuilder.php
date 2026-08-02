<?php

namespace App\Domain\Projection;

use App\Domain\Projection\Descriptors\BlockProjection;
use App\Domain\Projection\Descriptors\FieldType;
use App\Domain\Projection\Input\BlockNode;
use App\Domain\Projection\Input\BlockTree;
use Illuminate\Support\Arr;

/**
 * Derives the full internal projection from a block tree.
 *
 * PURE: no database, filesystem, network, cache, clock or randomness. Every
 * input arrives through the tree or the context. Given identical inputs it
 * returns a byte-identical result every time (Prime Directive 6). This purity
 * is what makes the golden tests possible.
 */
class ProjectionBuilder
{
    public const VERSION = '1.0';

    public function build(BlockTree $tree, ProjectionContext $ctx): Projection
    {
        $state = new BuildState();

        // The page title is the root of every heading path.
        if ($ctx->title !== '') {
            $state->headingStack[] = ['level' => 0, 'text' => $ctx->title];
        }

        $structure = [];
        foreach ($tree->roots() as $i => $node) {
            foreach ($this->walk($node, (string) $i, $ctx, $state) as $entry) {
                $structure[] = $entry;
            }
        }

        $data = [
            'projection_version' => self::VERSION,
            'source' => [
                'page_id' => $ctx->pageId,
                'page_version_id' => $ctx->pageVersionId,
                'content_hash' => $this->hashTree($tree->roots()),
                'built_at' => null, // added by the integration layer; null keeps the builder deterministic
            ],
            'page' => [
                'url' => $ctx->url,
                'title' => $ctx->title,
                'language' => $ctx->language,
                'published_at' => $ctx->publishedAt,
                'modified_at' => $ctx->modifiedAt,
            ],
            'structure' => $structure,
            'schema_org' => [
                '@context' => 'https://schema.org',
                '@graph' => $state->schemaNodes,
            ],
            'segments' => $state->segments,
            'inventory' => [
                'outbound_links' => $state->outboundLinks,
                'assets' => $state->assets,
                'heading_outline' => $state->headingOutline,
                'entity_refs' => $state->entityRefs,
                'word_count' => $state->wordCount,
            ],
            'collections' => [],
        ];

        return new Projection($data);
    }

    /**
     * Pre-order walk. Returns the structure entries this node contributes:
     *   - marked block  → one entry wrapping its (bubbled) children
     *   - unmarked block → its children's entries, bubbled up unchanged
     * so an unmarked parent never interrupts traversal (Prime Directive: a
     * descriptor-less parent with marked children still yields the children).
     *
     * @return list<array>
     */
    private function walk(BlockNode $node, string $path, ProjectionContext $ctx, BuildState $state): array
    {
        $proj = $ctx->descriptorFor($node->type);
        $depth = substr_count($path, '.') + 1;

        $fields = $proj !== null ? $this->collectFields($node, $proj, $ctx, $state, $path) : [];

        // Recurse. Child i of this node is at "$path.$i".
        $childEntries = [];
        foreach ($node->children as $i => $child) {
            foreach ($this->walk($child, $path . '.' . $i, $ctx, $state) as $entry) {
                $childEntries[] = $entry;
            }
        }

        // Unmarked block: contribute nothing itself, bubble children up.
        if ($proj === null) {
            return $childEntries;
        }

        return [[
            'address' => $node->address,
            'type' => $node->type,
            'path' => $path,
            'depth' => $depth,
            'fields' => $fields,
            'children' => $childEntries,
        ]];
    }

    /**
     * Process a marked block's declared fields: build the structure field map
     * and feed the semantic accumulators (segments, schema, inventory, words).
     * Only declared, non-empty, rendered fields participate (Prime Directives
     * 3 and 4).
     *
     * @return array<string,array{name:string,type:string,value:mixed}>
     */
    private function collectFields(BlockNode $node, BlockProjection $proj, ProjectionContext $ctx, BuildState $state, string $path): array
    {
        $fields = [];
        $ragTexts = [];
        $segmentAddress = null;

        // Heading contribution is derived from the block's data before its
        // segment (if any) is emitted, so segments below see the updated stack.
        $headingLevel = $proj->hasHeadingLevel() ? $proj->resolveHeadingLevel($node->data) : null;

        foreach ($proj->getFields() as $field) {
            $value = Arr::get($node->data, $field->path);
            if ($this->isEmpty($value)) {
                continue;
            }

            $address = $node->address . '#' . $field->path;
            if (! $ctx->fieldIsRendered($address)) {
                continue; // parity: not rendered → not projected
            }

            $fields[$address] = [
                'name' => $field->name,
                'type' => $field->type->value,
                'value' => $value,
            ];

            // --- RAG text ---------------------------------------------------
            if ($field->rag) {
                $text = $this->plainText((string) $value, $field->type);
                if ($text !== '') {
                    $ragTexts[] = $text;
                    $state->wordCount += $this->countWords($text);
                }
                if ($field->segment && $segmentAddress === null) {
                    $segmentAddress = $address;
                }
            }

            // --- Inventory: outbound links ----------------------------------
            if ($field->type === FieldType::Url) {
                $state->outboundLinks[] = [
                    'url' => (string) $value,
                    'address' => $address,
                    'internal' => $this->isInternal((string) $value),
                ];
            }
            if ($field->type === FieldType::RichText) {
                foreach ($this->extractLinks((string) $value) as $href) {
                    $state->outboundLinks[] = [
                        'url' => $href,
                        'address' => $address,
                        'internal' => $this->isInternal($href),
                    ];
                }
            }

            // --- Inventory: assets ------------------------------------------
            if ($field->inventory && $field->type === FieldType::AssetRef) {
                $state->assets[] = [
                    'asset_id' => (string) $value,
                    'address' => $address,
                    'role' => $field->name,
                ];
            }
        }

        // Heading outline + stack update (after fields so we have the text).
        if ($headingLevel !== null) {
            $headingText = trim(implode(' ', $ragTexts));
            if ($headingText !== '') {
                $state->headingOutline[] = [
                    'level' => $headingLevel,
                    'text' => $headingText,
                    'address' => $node->address,
                ];
                while (! empty($state->headingStack)
                    && $state->headingStack[array_key_last($state->headingStack)]['level'] >= $headingLevel) {
                    array_pop($state->headingStack);
                }
                $state->headingStack[] = ['level' => $headingLevel, 'text' => $headingText];
            }
        }

        // Segment emission for a boundary block.
        if ($proj->isSegmentBoundary()) {
            $segmentText = trim(implode("\n", $ragTexts));
            if ($segmentText !== '') {
                $address = $segmentAddress ?? $node->address;
                $state->segments[] = [
                    'id' => 'seg_' . substr(hash('sha256', $address), 0, 12),
                    'address' => $address,
                    'heading_path' => array_map(fn ($h) => $h['text'], $state->headingStack),
                    'text' => $segmentText,
                    'hash' => hash('sha256', $segmentText),
                ];
            }
        }

        // schema.org node for a block that declares a type and has ≥1 schema value.
        $schemaType = $proj->getSchemaType();
        if ($schemaType !== null) {
            $node_props = [];
            foreach ($proj->getFields() as $field) {
                if (! $field->schema) {
                    continue;
                }
                $value = Arr::get($node->data, $field->path);
                if ($this->isEmpty($value)) {
                    continue;
                }
                $address = $node->address . '#' . $field->path;
                if (! $ctx->fieldIsRendered($address)) {
                    continue;
                }
                $node_props[$field->name] = is_string($value) ? $this->plainText($value, $field->type) : $value;
            }
            if (! empty($node_props)) {
                $state->schemaNodes[] = ['@type' => $schemaType]
                    + $node_props
                    + ['stillopress:blockAddress' => $node->address];
            }
        }

        return $fields;
    }

    // --- helpers -----------------------------------------------------------

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Strip markup to plain text for RAG/schema; RichText is HTML, other text
     * types are treated as already-plain.
     */
    private function plainText(string $value, FieldType $type): string
    {
        if ($type === FieldType::RichText) {
            $value = strip_tags($value);
        }
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function countWords(string $text): int
    {
        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? 0 : count($parts);
    }

    /** @return list<string> */
    private function extractLinks(string $html): array
    {
        if (! preg_match_all('/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            return [];
        }

        return array_values($m[1]);
    }

    private function isInternal(string $url): bool
    {
        // Absolute (scheme:// or protocol-relative //) and mailto/tel are external.
        if (preg_match('#^(?:[a-z][a-z0-9+.\-]*:)?//#i', $url)) {
            return false;
        }
        if (preg_match('/^(?:mailto:|tel:)/i', $url)) {
            return false;
        }

        return true;
    }

    /** @param list<BlockNode> $nodes */
    private function hashTree(array $nodes): string
    {
        return hash('sha256', $this->canonicalize($nodes));
    }

    /** @param list<BlockNode> $nodes */
    private function canonicalize(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            $data = $node->data;
            $this->ksortRecursive($data);
            $parts[] = json_encode(
                ['a' => $node->address, 't' => $node->type, 'd' => $data],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) . '[' . $this->canonicalize($node->children) . ']';
        }

        return implode('|', $parts);
    }

    private function ksortRecursive(array &$arr): void
    {
        foreach ($arr as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
        unset($value);

        // Only sort associative arrays; preserve list order (arrays are ordered content).
        if ($arr !== [] && array_keys($arr) !== range(0, count($arr) - 1)) {
            ksort($arr);
        }
    }
}
