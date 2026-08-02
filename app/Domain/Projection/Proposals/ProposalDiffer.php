<?php

namespace App\Domain\Projection\Proposals;

use App\Domain\Projection\Input\BlockNode;
use App\Domain\Projection\Input\BlockTree;

/**
 * AI Change Proposals (first slice): compute a canonical, comparable diff
 * between the current content and a proposed set of field values, addressed by
 * the editor's canonical form {uuid}#{path}.
 *
 * Pure and deterministic: same inputs → same ops, sorted by address, so the
 * same proposal always serialises to the same bytes.
 *
 * Read-only — it never writes back to blocks. Applying an accepted proposal is
 * a separate, human-gated step.
 */
class ProposalDiffer
{
    /**
     * Flatten a block tree to canonical addressable fields:
     * {block_uuid}#{dot.path} => leaf value. Reserved `__*` keys (style,
     * animation, responsive, advanced) are not addressable and are skipped —
     * matching the editor's inline-edit rules.
     *
     * @return array<string,mixed>
     */
    public function flatten(BlockTree $tree): array
    {
        $out = [];
        foreach ($tree->roots() as $node) {
            $this->flattenNode($node, $out);
        }
        ksort($out);

        return $out;
    }

    /**
     * Diff proposed values against the current ones. Only addresses present in
     * $proposed are considered (a proposal is a sparse set of edits).
     *
     * @param array<string,mixed> $current  address => current value
     * @param array<string,mixed> $proposed address => proposed value
     * @return list<ChangeOp> ops sorted by address (deterministic)
     */
    public function diff(array $current, array $proposed): array
    {
        $ops = [];
        $addresses = array_keys($proposed);
        sort($addresses);

        foreach ($addresses as $address) {
            $after = $proposed[$address];
            $exists = array_key_exists($address, $current);
            $before = $exists ? $current[$address] : null;

            if ($after === null && $exists) {
                $ops[] = new ChangeOp($address, ChangeOp::UNSET, $before, null);
                continue;
            }
            if (! $exists || $before !== $after) {
                $ops[] = new ChangeOp($address, ChangeOp::SET, $before, $after);
            }
        }

        return $ops;
    }

    /**
     * Validate that every op addresses a field the editor can resolve: the
     * block must exist, and for edits/unsets to existing content the exact
     * field path must exist. Returns the addresses that do NOT resolve.
     *
     * @param list<ChangeOp>      $ops
     * @param array<string,mixed> $current flattened current fields (from flatten())
     * @return list<string> unresolvable addresses
     */
    public function validate(array $ops, array $current): array
    {
        $knownBlocks = [];
        foreach (array_keys($current) as $addr) {
            $knownBlocks[explode('#', $addr, 2)[0]] = true;
        }

        $invalid = [];
        foreach ($ops as $op) {
            [$blockId] = explode('#', $op->address, 2) + [null];

            // Unknown block → unresolvable.
            if (! isset($knownBlocks[$blockId])) {
                $invalid[] = $op->address;
                continue;
            }
            // Removing/editing a field that doesn't exist on a known block.
            if ($op->op === ChangeOp::UNSET && ! array_key_exists($op->address, $current)) {
                $invalid[] = $op->address;
            }
        }

        return $invalid;
    }

    /** @param array<string,mixed> $out */
    private function flattenNode(BlockNode $node, array &$out): void
    {
        foreach ($this->flattenData($node->data) as $path => $value) {
            $out[$node->address . '#' . $path] = $value;
        }
        foreach ($node->children as $child) {
            $this->flattenNode($child, $out);
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed> dot-path => leaf value
     */
    private function flattenData(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, '__')) {
                continue; // reserved structural keys are not addressable
            }
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value) && $value !== []) {
                foreach ($this->flattenData($value, $path) as $k => $v) {
                    $out[$k] = $v;
                }
            } else {
                $out[$path] = $value;
            }
        }

        return $out;
    }
}
