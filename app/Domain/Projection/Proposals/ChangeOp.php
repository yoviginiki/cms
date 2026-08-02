<?php

namespace App\Domain\Projection\Proposals;

/**
 * One operation in an AI change proposal, addressed by the SAME canonical form
 * the editor uses: {block_uuid}#{dot.path.into.data} (no `data.` prefix). A
 * diff that points at an address the editor cannot resolve is useless, so the
 * address here is exactly `data-sp-field`'s.
 */
final class ChangeOp
{
    public const SET = 'set';
    public const UNSET = 'unset';

    public function __construct(
        public readonly string $address,
        public readonly string $op,
        public readonly mixed $before,
        public readonly mixed $after,
    ) {
    }

    /** Canonical, stable array form (fixed key order) for serialization. */
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'op' => $this->op,
            'before' => $this->before,
            'after' => $this->after,
        ];
    }
}
