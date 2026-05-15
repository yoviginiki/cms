<?php

namespace Tests\Feature\Blocks;

use App\Domain\Blocks\Enums\BlockLevel;
use App\Support\Blocks\HierarchyValidator;
use Tests\TestCase;

class HierarchyValidatorTest extends TestCase
{
    // ── BlockLevel enum ──

    public function test_section_allows_only_rows(): void
    {
        $this->assertEquals([BlockLevel::Row], BlockLevel::Section->allowedChildLevels());
    }

    public function test_row_allows_only_columns(): void
    {
        $this->assertEquals([BlockLevel::Column], BlockLevel::Row->allowedChildLevels());
    }

    public function test_column_allows_only_modules(): void
    {
        $this->assertEquals([BlockLevel::Module], BlockLevel::Column->allowedChildLevels());
    }

    public function test_module_allows_no_children(): void
    {
        $this->assertEquals([], BlockLevel::Module->allowedChildLevels());
    }

    public function test_only_section_can_be_root(): void
    {
        $this->assertTrue(BlockLevel::Section->canBeRoot());
        $this->assertFalse(BlockLevel::Row->canBeRoot());
        $this->assertFalse(BlockLevel::Column->canBeRoot());
        // Module at root is handled specially in validator (backward compat)
    }

    // ── Valid hierarchies ──

    public function test_valid_hierarchy_passes(): void
    {
        $blocks = json_decode(file_get_contents(
            base_path('tests/fixtures/blocks/valid-hierarchy.json')
        ), true);

        $result = HierarchyValidator::validate($blocks);

        $this->assertTrue($result->valid, 'Valid hierarchy should pass. Errors: ' . implode(', ', $result->errorMessages()));
        $this->assertEmpty($result->errors);
    }

    public function test_empty_tree_is_valid(): void
    {
        $result = HierarchyValidator::validate([]);
        $this->assertTrue($result->valid);
    }

    public function test_missing_level_defaults_to_module(): void
    {
        // Legacy blocks may not have the 'level' field — should default to 'module'
        $blocks = [
            ['id' => 'mod-1', 'type' => 'heading', 'order' => 0, 'data' => [], 'children' => []],
        ];

        $result = HierarchyValidator::validate($blocks);
        $this->assertTrue($result->valid, 'Block without level field should default to module and pass');
    }

    public function test_module_at_root_is_valid_for_backward_compat(): void
    {
        $blocks = [
            ['id' => 'mod-1', 'type' => 'heading', 'level' => 'module', 'order' => 0, 'data' => [], 'children' => []],
        ];

        $result = HierarchyValidator::validate($blocks);
        $this->assertTrue($result->valid, 'Module at root should be allowed for backward compatibility');
    }

    // ── Invalid hierarchies ──

    public function test_module_in_section_fails(): void
    {
        $fixtures = json_decode(file_get_contents(
            base_path('tests/fixtures/blocks/invalid-hierarchy.json')
        ), true);

        $result = HierarchyValidator::validate($fixtures['module_in_section']);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Module cannot be inside Section', $result->errors[0]['message']);
    }

    public function test_row_in_row_fails(): void
    {
        $fixtures = json_decode(file_get_contents(
            base_path('tests/fixtures/blocks/invalid-hierarchy.json')
        ), true);

        $result = HierarchyValidator::validate($fixtures['row_in_row']);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Row cannot be inside Row', $result->errors[0]['message']);
    }

    public function test_column_at_root_fails(): void
    {
        $fixtures = json_decode(file_get_contents(
            base_path('tests/fixtures/blocks/invalid-hierarchy.json')
        ), true);

        $result = HierarchyValidator::validate($fixtures['column_at_root']);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('cannot be at root level', $result->errors[0]['message']);
    }

    public function test_row_at_root_fails(): void
    {
        $fixtures = json_decode(file_get_contents(
            base_path('tests/fixtures/blocks/invalid-hierarchy.json')
        ), true);

        $result = HierarchyValidator::validate($fixtures['row_at_root']);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('cannot be at root level', $result->errors[0]['message']);
    }

    public function test_module_with_children_fails(): void
    {
        $fixtures = json_decode(file_get_contents(
            base_path('tests/fixtures/blocks/invalid-hierarchy.json')
        ), true);

        $result = HierarchyValidator::validate($fixtures['module_with_children']);

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('cannot have children', $result->errors[0]['message']);
    }

    // ── Error path reporting ──

    public function test_error_includes_path(): void
    {
        $blocks = [
            [
                'id' => 'sec-1', 'type' => 'section', 'level' => 'section', 'order' => 0, 'data' => [],
                'children' => [
                    [
                        'id' => 'bad', 'type' => 'heading', 'level' => 'module', 'order' => 0, 'data' => [],
                        'children' => [],
                    ],
                ],
            ],
        ];

        $result = HierarchyValidator::validate($blocks);

        $this->assertFalse($result->valid);
        $this->assertEquals('blocks[0].children[0]', $result->errors[0]['path']);
    }
}
