<?php

namespace App\Domain\Projection;

/**
 * @internal Mutable accumulator threaded through the builder's tree walk.
 * Object semantics let the recursion mutate shared collections without a long
 * list of by-reference parameters. Never leaves the builder.
 */
final class BuildState
{
    /** @var list<array> */
    public array $segments = [];

    /** @var list<array> */
    public array $schemaNodes = [];

    /** @var list<array> */
    public array $outboundLinks = [];

    /** @var list<array> */
    public array $assets = [];

    /** @var list<array> */
    public array $headingOutline = [];

    /** @var list<array> */
    public array $entityRefs = [];

    public int $wordCount = 0;

    /** @var list<array{level:int,text:string}> Current heading stack for RAG heading paths. */
    public array $headingStack = [];
}
