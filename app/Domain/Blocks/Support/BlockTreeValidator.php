<?php

namespace App\Domain\Blocks\Support;

use App\Domain\Blocks\Exceptions\InvalidBlockTreeException;
use App\Domain\Blocks\Services\BlockRegistry;
use Illuminate\Support\Facades\Validator;

/**
 * Per-node validation of a block tree against the REGISTERED definitions
 * (F14, audit 2026-09-22). Runs inside the central write service, so every
 * caller — HTTP sync, version restore, wizards, imports — is covered:
 *
 *  - every node's type must be a registered block type;
 *  - every node's data is validated with the definition's validationRules()
 *    for shape and format (max/in/regex/uuid/not_regex …). Presence rules
 *    (`required*`) are NOT enforced on save: a freshly added block is
 *    legitimately incomplete while it is being edited (autosave), and
 *    completeness is a publish-time concern. Format violations, unknown
 *    types and oversized payloads are refused before any write;
 *  - the child contract of the definition (allowsChildren).
 *
 * Trusted programmatic writers (seeders, importers) may relax the field
 * rules with `$rules = false`, but never the type check.
 */
final class BlockTreeValidator
{
    public const MAX_NODES = 2000;
    public const MAX_DEPTH = 8;

    /** @var array<string,array> shape rules per block type (computed once) */
    private array $shapeCache = [];

    /** @var array<string,array<int,array{0:string,1:array}>> nodes awaiting field validation, by type */
    private array $pending = [];

    /**
     * Validate pending nodes one by one, but only against the rules for the
     * fields each node actually carries (H03). Every shape rule is optional
     * ('sometimes'), so rules for absent fields can never fail — skipping
     * them removes most of the per-node Validator cost.
     */
    private function validatePending(array &$errors): void
    {
        foreach ($this->pending as $type => $items) {
            $definition = $this->registry->get($type);
            $this->shapeCache[$type] ??= $this->shapeRules($definition->validationRules());
            $all = $this->shapeCache[$type];
            foreach ($items as [$path, $data]) {
                $present = array_flip(array_map('strval', array_keys($data)));
                $rules = array_filter($all, fn ($field) => isset($present[explode('.', (string) $field, 2)[0]]), ARRAY_FILTER_USE_KEY);
                if ($rules === []) {
                    continue;
                }
                $validator = Validator::make($data, $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->toArray() as $field => $messages) {
                        $errors["{$path}.data.{$field}"] = array_values($messages);
                    }
                }
            }
        }
        $this->pending = [];
    }

    public function __construct(private BlockRegistry $registry)
    {
    }

    /**
     * @return array<string,array<int,string>> errors keyed by path (empty = valid)
     */
    public function validate(array $tree, bool $rules = true): array
    {
        $errors = [];
        $count = 0;
        $this->pending = [];
        $this->walk($tree, 'blocks', 1, $rules, $errors, $count);
        $this->validatePending($errors);
        if ($count > self::MAX_NODES) {
            $errors['blocks'][] = 'Too many blocks (max ' . self::MAX_NODES . ').';
        }

        return $errors;
    }

    /** @throws InvalidBlockTreeException */
    public function assertValid(array $tree, bool $rules = true): void
    {
        $errors = $this->validate($tree, $rules);
        if ($errors !== []) {
            throw new InvalidBlockTreeException($errors);
        }
    }

    private function walk(array $nodes, string $path, int $depth, bool $rules, array &$errors, int &$count): void
    {
        if ($depth > self::MAX_DEPTH) {
            $errors[$path][] = 'Nesting too deep (max ' . self::MAX_DEPTH . ').';

            return;
        }
        foreach (array_values($nodes) as $i => $node) {
            $count++;
            $p = "{$path}.{$i}";
            if (!is_array($node)) {
                $errors[$p][] = 'Block must be an object.';
                continue;
            }
            $type = $node['type'] ?? null;
            if (!is_string($type) || $type === '') {
                $errors["{$p}.type"][] = 'Block type is required.';
                continue;
            }
            $definition = $this->registry->get($type);
            if ($definition === null) {
                $errors["{$p}.type"][] = "Unknown block type '{$type}'.";
                continue;
            }
            $data = $node['data'] ?? [];
            if (!is_array($data)) {
                $errors["{$p}.data"][] = 'Block data must be an object.';
                continue;
            }
            if ($rules && $data !== []) { // empty data satisfies shape-only rules
                // Batched per type after the walk (H03: one validator per TYPE,
                // not per node — 2.2 s → a fraction for a 500-block page).
                $this->pending[$type][] = [$p, $data];
            }
            $children = $node['children'] ?? [];
            if (!is_array($children)) {
                $errors["{$p}.children"][] = 'Children must be a list.';
                continue;
            }
            if ($children !== []) {
                if (!$definition->allowsChildren()) {
                    $errors["{$p}.children"][] = "Block type '{$type}' does not allow children.";
                    continue;
                }
                // maxChildren() is NOT enforced on save: the existing values (6/10/20/…)
                // are editor hints, and real pages (long articles, the docs site)
                // already exceed them. Inventory item for the block-contract review.
                $this->walk($children, "{$p}.children", $depth + 1, $rules, $errors, $count);
            }
        }
    }

    /** Same rules minus presence requirements (see class doc). */
    private function shapeRules(array $rules): array
    {
        $out = [];
        foreach ($rules as $field => $set) {
            $set = is_string($set) ? explode('|', $set) : (array) $set;
            $kept = [];
            foreach ($set as $rule) {
                if (is_string($rule) && preg_match('/^(required|required_with|required_without|required_if|required_unless|required_with_all|required_without_all|present|filled)\b/', $rule)) {
                    continue;
                }
                $kept[] = $rule;
            }
            if ($kept !== [] && !in_array('sometimes', $kept, true) && !in_array('nullable', $kept, true)) {
                array_unshift($kept, 'sometimes');
            } elseif ($kept !== [] && !in_array('sometimes', $kept, true)) {
                array_unshift($kept, 'sometimes');
            }
            $out[$field] = $kept;
        }

        return $out;
    }
}
