<?php

namespace Tests\Unit\References;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\References\Services\ReferenceExtractorRegistry;
use Tests\TestCase;

/**
 * Registry integrity: reference extraction is part of the block registry
 * contract. Every registered block type MUST have an extractor (NullExtractor
 * counts; omission fails). Stale extractor entries for unregistered types
 * fail too, so the two registries can never drift apart.
 */
class ExtractorCoverageTest extends TestCase
{
    public function test_every_registered_block_type_has_a_reference_extractor(): void
    {
        $blockTypes = array_column(app(BlockRegistry::class)->getAllTypes(), 'type');
        $extractors = new ReferenceExtractorRegistry();

        $missing = array_diff($blockTypes, $extractors->coveredTypes());

        $this->assertSame(
            [],
            array_values($missing),
            'Block types without a reference extractor (add one to ReferenceExtractorRegistry — NullExtractor if the block references nothing): '
                . implode(', ', $missing),
        );
    }

    public function test_no_extractor_is_registered_for_an_unknown_block_type(): void
    {
        $blockTypes = array_column(app(BlockRegistry::class)->getAllTypes(), 'type');
        $extractors = new ReferenceExtractorRegistry();

        $stale = array_diff($extractors->coveredTypes(), $blockTypes);

        $this->assertSame(
            [],
            array_values($stale),
            'Extractor entries for block types that are not registered in BlockRegistry: '
                . implode(', ', $stale),
        );
    }
}
