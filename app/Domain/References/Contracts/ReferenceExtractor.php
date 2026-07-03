<?php

namespace App\Domain\References\Contracts;

use App\Domain\References\ExtractionContext;

/**
 * Extracts entity-reference edges from a single block's data payload.
 *
 * Part of the block registry contract: EVERY registered block type must have
 * an extractor in ReferenceExtractorRegistry (NullExtractor when the block
 * references nothing). ExtractorCoverageTest enforces this.
 */
interface ReferenceExtractor
{
    /**
     * @return array<int, array{target_type: string, target_id: ?string, kind: string}>
     */
    public function extract(array $data, ExtractionContext $context): array;
}
