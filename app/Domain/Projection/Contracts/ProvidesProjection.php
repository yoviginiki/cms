<?php

namespace App\Domain\Projection\Contracts;

use App\Domain\Projection\Descriptors\BlockProjection;

/**
 * Opt-in semantic contract for the Content Projection Layer.
 *
 * A block definition implements this ONLY when it wants to declare what it
 * means. This is deliberately a separate interface from
 * {@see \App\Domain\Blocks\Definitions\BlockDefinition} so that the ~100
 * existing block definitions remain untouched — a block that does not
 * implement this contributes nothing to any projection (Prime Directive 3:
 * "never guess semantics").
 */
interface ProvidesProjection
{
    /**
     * Declare how this block projects into the machine-readable views, or
     * null if the block carries no semantic meaning. Returning null is the
     * default for every block that does not opt in.
     */
    public function projection(): ?BlockProjection;
}
