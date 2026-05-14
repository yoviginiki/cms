<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\FlipbookBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FlipbookValidationTest extends TestCase
{
    private FlipbookBlockDefinition $def;
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->def = new FlipbookBlockDefinition();
        $this->rules = $this->def->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_definition_type(): void
    {
        $this->assertEquals('flipbook', $this->def->type());
    }

    public function test_allows_children(): void
    {
        $this->assertTrue($this->def->allowsChildren());
    }

    public function test_empty_data_passes(): void
    {
        $this->assertTrue($this->validate([])->passes());
    }

    public function test_valid_mode_passes(): void
    {
        $this->assertTrue($this->validate(['mode' => 'realistic'])->passes());
    }

    public function test_invalid_mode_fails(): void
    {
        $this->assertTrue($this->validate(['mode' => '__invalid__'])->fails());
    }

    public function test_valid_aspect_ratio_passes(): void
    {
        $this->assertTrue($this->validate(['aspect_ratio' => '1:1'])->passes());
    }

    public function test_invalid_aspect_ratio_fails(): void
    {
        $this->assertTrue($this->validate(['aspect_ratio' => '__invalid__'])->fails());
    }

    public function test_custom_width_px_in_range(): void
    {
        $this->assertTrue($this->validate(['custom_width_px' => 100])->passes());
        $this->assertTrue($this->validate(['custom_width_px' => 4000])->passes());
    }

    public function test_custom_width_px_out_of_range(): void
    {
        $this->assertTrue($this->validate(['custom_width_px' => 100 - 1])->fails());
        $this->assertTrue($this->validate(['custom_width_px' => 4000 + 1])->fails());
    }

    public function test_custom_height_px_in_range(): void
    {
        $this->assertTrue($this->validate(['custom_height_px' => 100])->passes());
        $this->assertTrue($this->validate(['custom_height_px' => 4000])->passes());
    }

    public function test_custom_height_px_out_of_range(): void
    {
        $this->assertTrue($this->validate(['custom_height_px' => 100 - 1])->fails());
        $this->assertTrue($this->validate(['custom_height_px' => 4000 + 1])->fails());
    }

    public function test_flipping_time_ms_in_range(): void
    {
        $this->assertTrue($this->validate(['flipping_time_ms' => 200])->passes());
        $this->assertTrue($this->validate(['flipping_time_ms' => 2000])->passes());
    }

    public function test_flipping_time_ms_out_of_range(): void
    {
        $this->assertTrue($this->validate(['flipping_time_ms' => 200 - 1])->fails());
        $this->assertTrue($this->validate(['flipping_time_ms' => 2000 + 1])->fails());
    }

    public function test_show_cover_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['show_cover' => true])->passes());
        $this->assertTrue($this->validate(['show_cover' => false])->passes());
    }

    public function test_click_to_flip_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['click_to_flip' => true])->passes());
        $this->assertTrue($this->validate(['click_to_flip' => false])->passes());
    }

    public function test_swipe_to_flip_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['swipe_to_flip' => true])->passes());
        $this->assertTrue($this->validate(['swipe_to_flip' => false])->passes());
    }

    public function test_show_nav_bar_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['show_nav_bar' => true])->passes());
        $this->assertTrue($this->validate(['show_nav_bar' => false])->passes());
    }

    public function test_show_fullscreen_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['show_fullscreen' => true])->passes());
        $this->assertTrue($this->validate(['show_fullscreen' => false])->passes());
    }

    public function test_show_page_indicator_accepts_boolean(): void
    {
        $this->assertTrue($this->validate(['show_page_indicator' => true])->passes());
        $this->assertTrue($this->validate(['show_page_indicator' => false])->passes());
    }

    public function test_valid_source_passes(): void
    {
        $this->assertTrue($this->validate(['source' => 'children'])->passes());
    }

    public function test_invalid_source_fails(): void
    {
        $this->assertTrue($this->validate(['source' => '__invalid__'])->fails());
    }

    public function test_valid_posts_order_passes(): void
    {
        $this->assertTrue($this->validate(['posts_order' => 'date_desc'])->passes());
    }

    public function test_invalid_posts_order_fails(): void
    {
        $this->assertTrue($this->validate(['posts_order' => '__invalid__'])->fails());
    }

    public function test_posts_limit_in_range(): void
    {
        $this->assertTrue($this->validate(['posts_limit' => 2])->passes());
        $this->assertTrue($this->validate(['posts_limit' => 200])->passes());
    }

    public function test_posts_limit_out_of_range(): void
    {
        $this->assertTrue($this->validate(['posts_limit' => 2 - 1])->fails());
        $this->assertTrue($this->validate(['posts_limit' => 200 + 1])->fails());
    }
}
