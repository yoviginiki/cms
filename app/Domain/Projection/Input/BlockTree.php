<?php

namespace App\Domain\Projection\Input;

/**
 * The ordered set of root blocks for a page, as a pure input to the builder.
 */
final class BlockTree
{
    /** @param list<BlockNode> $roots */
    public function __construct(
        private readonly array $roots = [],
    ) {
    }

    /** @return list<BlockNode> */
    public function roots(): array
    {
        return $this->roots;
    }
}
