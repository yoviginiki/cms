<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\LinearGalleryBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LinearGalleryValidationTest extends TestCase
{
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = (new LinearGalleryBlockDefinition())->validationRules();
    }

    private function validate(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $this->rules);
    }

    public function test_type_and_children(): void
    {
        $def = new LinearGalleryBlockDefinition();
        $this->assertSame('linear-gallery', $def->type());
        $this->assertSame('media', $def->category());
        $this->assertFalse($def->allowsChildren());
    }

    public function test_empty_and_full_data_pass(): void
    {
        $this->assertTrue($this->validate([])->passes());
        $this->assertTrue($this->validate([
            'images' => ['/legacy.jpg', ['id' => 'x', 'src' => '/a.jpg', 'alt' => 'A', 'caption' => 'c', 'link' => '/p/', 'width' => 800, 'height' => 600]],
            'height' => 360, 'mobileHeight' => 220, 'sizeVariation' => 'strong', 'overlap' => 22, 'scatter' => 24,
            'blend' => 'multiply', 'opacity' => 92, 'background' => '#a2a093', 'hoverBorderColor' => 'var(--color-primary)',
            'hoverBorderWidth' => 3, 'hoverShadow' => 'strong', 'hoverLift' => true, 'arrows' => true, 'autoplay' => 0,
            'lightbox' => true, 'align' => 'center', 'padding' => 40, 'width' => 'full', 'stripHeight' => 0,
            'offsetStart' => 24, 'offsetEnd' => 24, 'radius' => 4, 'borderColor' => '#fff', 'borderWidth' => 1, 'shadow' => 'soft',
            'hoverGlow' => true, 'arrowsShow' => 'always', 'arrowsPosition' => 'bottom-right', 'arrowsSize' => 'md',
            'arrowsColor' => '#111', 'arrowsBg' => 'rgba(255,255,255,.8)', 'arrowsMobile' => true, 'drag' => true, 'scrollbar' => false, 'openOn' => 'dblclick',
        ])->passes());
        // legacy boolean hoverShadow from the first release still validates
        $this->assertTrue($this->validate(['hoverShadow' => true])->passes());
    }

    public function test_rejects_bad_values(): void
    {
        $this->assertTrue($this->validate(['images' => [['src' => 'javascript:alert(1)']]])->fails());
        $this->assertTrue($this->validate(['images' => [123]])->fails());
        $this->assertTrue($this->validate(['blend' => 'overlay'])->fails());
        $this->assertTrue($this->validate(['height' => 50])->fails());
        $this->assertTrue($this->validate(['opacity' => 10])->fails());
        $this->assertTrue($this->validate(['background' => 'url(x)'])->fails());
        $this->assertTrue($this->validate(['hoverBorderColor' => '#zzz; x'])->fails());
        $this->assertTrue($this->validate(['shadow' => 'huge'])->fails());
        $this->assertTrue($this->validate(['hoverShadow' => 'huge'])->fails());
        $this->assertTrue($this->validate(['width' => 'wide'])->fails());
        $this->assertTrue($this->validate(['arrowsPosition' => 'left'])->fails());
        $this->assertTrue($this->validate(['openOn' => 'hover'])->fails());
    }
}
