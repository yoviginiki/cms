<?php

namespace App\Domain\Projection\Input;

/**
 * A pure, immutable representation of one block for the projection builder.
 *
 * This is deliberately decoupled from the Eloquent Block model: the builder
 * must never touch the database. The integration layer (Phase 4) converts the
 * persisted block tree into BlockNode trees.
 */
final class BlockNode
{
    /**
     * @param string          $address The block UUID (blocks.id).
     * @param string          $type    The registry block type (e.g. 'heading').
     * @param array           $data    The block's `data` payload.
     * @param list<BlockNode> $children Child blocks, in document order.
     */
    public function __construct(
        public readonly string $address,
        public readonly string $type,
        public readonly array $data = [],
        public readonly array $children = [],
    ) {
    }
}
