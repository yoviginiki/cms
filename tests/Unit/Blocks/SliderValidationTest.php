<?php

namespace Tests\Unit\Blocks;

use App\Domain\Blocks\Definitions\ShapeBlockDefinition;
use App\Domain\Blocks\Definitions\SlideBlockDefinition;
use App\Domain\Blocks\Definitions\SliderBlockDefinition;
use App\Domain\Blocks\Definitions\SliderRefBlockDefinition;
use App\Domain\Blocks\Definitions\TextBlockDefinition;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Slider schema sanitization: the animation/layout allowlists mirror the
 * reference prototype's SPEC NOTES — anything outside them is rejected before
 * it can reach the published JSON blob.
 */
class SliderValidationTest extends TestCase
{
    private function validate(object $def, array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $def->validationRules());
    }

    public function test_slider_root_accepts_prototype_shaped_config(): void
    {
        $v = $this->validate(new SliderBlockDefinition(), [
            'height' => ['desktop' => '70vh', 'tablet' => '60vh', 'mobile' => '80vh'],
            'swiper' => [
                'effect' => 'slide', 'speed' => 700, 'loop' => true,
                'autoplay' => true, 'autoplayDelay' => 6000,
                'navigation' => true, 'pagination' => true,
                'keyboard' => true, 'pauseOnHover' => true,
            ],
        ]);
        $this->assertTrue($v->passes(), json_encode($v->errors()->all()));
    }

    public function test_slider_root_rejects_unsafe_values(): void
    {
        $this->assertTrue($this->validate(new SliderBlockDefinition(), [
            'height' => ['desktop' => 'calc(100vh - 1px); position:fixed'],
        ])->fails());
        $this->assertTrue($this->validate(new SliderBlockDefinition(), [
            'swiper' => ['effect' => 'cube'],
        ])->fails());
        $this->assertTrue($this->validate(new SliderBlockDefinition(), [
            'swiper' => ['autoplayDelay' => 50],
        ])->fails());
    }

    public function test_slide_accepts_backgrounds_and_rejects_script_urls(): void
    {
        $ok = $this->validate(new SlideBlockDefinition(), [
            'background' => [
                'type' => 'image',
                'assetId' => '019e61f8-5b97-70ce-9261-d7ae9ff178cc',
                'overlay' => 'linear-gradient(90deg, rgba(26,24,23,.72) 0%, rgba(26,24,23,.15) 70%)',
                'kenBurns' => true,
            ],
            'duration' => 6000,
        ]);
        $this->assertTrue($ok->passes(), json_encode($ok->errors()->all()));

        $this->assertTrue($this->validate(new SlideBlockDefinition(), [
            'background' => ['src' => 'javascript:alert(1)'],
        ])->fails());
        $this->assertTrue($this->validate(new SlideBlockDefinition(), [
            'background' => ['overlay' => 'url(javascript:alert(1))'],
        ])->fails());
    }

    public function test_slider_ref_is_picker_plus_height_only(): void
    {
        $def = new SliderRefBlockDefinition();
        $this->assertTrue($this->validate($def, [
            'sliderId' => '019e61f8-5b97-70ce-9261-d7ae9ff178cc',
            'heightOverride' => ['desktop' => '80vh'],
        ])->passes());
        $this->assertTrue($this->validate($def, ['sliderId' => 'not-a-uuid'])->fails());
        $this->assertFalse($def->allowsChildren());
    }

    public function test_layer_animation_accepts_prototype_scene_shape(): void
    {
        // rules live on the existing primitives via SliderAnimation
        $v = $this->validate(new TextBlockDefinition(), [
            'content' => 'Paper & Mountain',
            'layout' => ['x' => '8%', 'y' => '32%', 'rotation' => -6, 'zIndex' => 3],
            'animation' => [
                'split' => 'chars',
                'in' => ['preset' => 'fadeUp', 'delay' => 0.3, 'duration' => 0.8, 'stagger' => 0.028],
                'loop' => ['tracks' => [[
                    'attr' => 'y', 'from' => '0', 'to' => '-10',
                    'duration' => 2.6, 'ease' => 'sine.inOut', 'yoyo' => true, 'repeat' => -1,
                ]]],
                'out' => ['preset' => 'fadeUp-out', 'duration' => 0.4],
                'trigger' => ['action' => 'goToSlide', 'target' => '2'],
            ],
        ]);
        $this->assertTrue($v->passes(), json_encode($v->errors()->all()));
    }

    public function test_layer_animation_rejects_outside_the_allowlists(): void
    {
        $bad = fn (array $animation) => $this->validate(new TextBlockDefinition(), [
            'content' => 'x', 'animation' => $animation,
        ])->fails();

        $this->assertTrue($bad(['in' => ['preset' => 'spinArtifact']]), 'unknown preset');
        $this->assertTrue($bad(['in' => ['tracks' => [['attr' => 'onclick', 'to' => '1', 'duration' => 1]]]]), 'attr outside allowlist');
        $this->assertTrue($bad(['in' => ['tracks' => [['attr' => 'x', 'to' => '1', 'duration' => 1, 'ease' => 'steps(40)']]]]), 'ease outside allowlist');
        $this->assertTrue($bad(['in' => ['duration' => 99]]), 'duration bound');
        $this->assertTrue($bad(['split' => 'paragraphs']), 'split mode');
        $this->assertTrue($bad(['trigger' => ['action' => 'eval']]), 'trigger action');
        $this->assertTrue($bad(['trigger' => ['action' => 'link', 'target' => 'javascript:alert(1)']]), 'trigger url scheme');
    }

    public function test_shape_layer_validates(): void
    {
        $def = new ShapeBlockDefinition();
        $this->assertSame('shape', $def->type());
        $this->assertTrue($this->validate($def, [
            'color' => '#E63B2E',
            'layout' => ['x' => '0px', 'y' => '70%', 'widthPct' => 42, 'heightPct' => 10, 'zIndex' => 1],
        ])->passes());
        $this->assertTrue($this->validate($def, [
            'layout' => ['x' => 'expression(alert(1))'],
        ])->fails());
    }
}
