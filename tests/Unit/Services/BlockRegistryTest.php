<?php

namespace Tests\Unit\Services;

use App\Domain\Blocks\Services\BlockRegistry;
use Tests\TestCase;

class BlockRegistryTest extends TestCase
{
    private function registry(): BlockRegistry
    {
        return app(BlockRegistry::class);
    }

    public function test_can_register_block_type(): void
    {
        $reg = $this->registry();
        $this->assertTrue($reg->has('hero'));
        $this->assertNotNull($reg->get('hero'));
        $this->assertSame('hero', $reg->get('hero')->type());
        $this->assertNull($reg->get('does-not-exist'));
    }

    public function test_get_all_types(): void
    {
        $types = $this->registry()->getAllTypes();
        $this->assertNotEmpty($types);
        $this->assertContains('hero', array_column($types, 'type'));
    }

    public function test_get_by_category(): void
    {
        $reg = $this->registry();
        $category = $reg->get('hero')->category();
        $inCategory = $reg->getByCategory($category);

        $this->assertNotEmpty($inCategory);
        $this->assertContains('hero', array_column($inCategory, 'type'));
    }

    public function test_validate_block_data(): void
    {
        $reg = $this->registry();
        // section.padding is an enum (none/sm/md/lg/xl)
        $this->assertTrue($reg->validate('section', ['padding' => 'md']));
        $this->assertFalse($reg->validate('section', ['padding' => 'ginormous']));
    }
}
