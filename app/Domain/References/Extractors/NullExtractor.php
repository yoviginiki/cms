<?php

namespace App\Domain\References\Extractors;

use App\Domain\References\Contracts\ReferenceExtractor;
use App\Domain\References\ExtractionContext;

/**
 * For block types that reference no other entities. Registering this (rather
 * than omitting the type) keeps extractor coverage explicit and testable.
 */
class NullExtractor implements ReferenceExtractor
{
    public function extract(array $data, ExtractionContext $context): array
    {
        return [];
    }
}
