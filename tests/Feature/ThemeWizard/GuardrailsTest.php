<?php

namespace Tests\Feature\ThemeWizard;

use App\Services\ThemeWizard\FontAllowlist;
use App\Services\ThemeWizard\Guardrails;
use App\Services\ThemeWizard\ThemeConversationEngine;
use App\Services\ThemeWizard\ThemeNudgeEngine;
use App\Services\ThemeWizard\ThemeVisionAnalyzer;
use App\Services\ThemeWizard\TokenProfileCompiler;
use App\Services\ThemeWizard\TokenProfileSchema;
use Tests\TestCase;

/**
 * W5: the "inspired, not copied" guardrails. Two are enforced STRUCTURALLY
 * (not just by prompt) — this proves them, plus that the shared prompt block
 * carries the judgement rules and reaches every engine.
 */
class GuardrailsTest extends TestCase
{
    private function profile(string $displayChar, string $bodyChar): array
    {
        return [
            'name' => 'T', 'design_read' => 'x',
            'palette' => [
                'brand' => '#1B4965', 'accent' => '#BC4B51', 'background' => '#F7F9FB', 'surface' => '#EDF1F5',
                'text' => '#2B3440', 'heading' => '#14202B', 'muted' => '#7C8996', 'border' => '#DCE3EA',
            ],
            'typography' => ['display_character' => $displayChar, 'body_character' => $bodyChar, 'scale' => 'balanced', 'heading_weight' => 600],
            'spacing' => 'balanced', 'radius' => 'soft', 'shadow' => 'subtle', 'layout' => 'business',
        ];
    }

    /** A reference's (possibly licensed) font can NEVER reach the theme —
        type is described by character and always substituted from the allowlist. */
    public function test_compiled_fonts_are_always_open_allowlisted(): void
    {
        $compiler = new TokenProfileCompiler();
        foreach ([
            ['a high-contrast serif exactly like Canela and GT Sectra', 'body like Helvetica Neue'],
            ['Proxima Nova geometric sans', 'Circular'],
            ['nonsense zzz qqq', ''],
        ] as [$d, $b]) {
            $doc = $compiler->compile($this->profile($d, $b))['document'];
            $display = $doc['semantic']['font']['family']['display']['$value'][0];
            $body = $doc['semantic']['font']['family']['body']['$value'][0];
            $this->assertTrue(FontAllowlist::isAllowed($display), "display font '{$display}' must be open/allowlisted");
            $this->assertTrue(FontAllowlist::isAllowed($body), "body font '{$body}' must be open/allowlisted");
            // and never the named reference fonts
            foreach (['Canela', 'GT Sectra', 'Proxima Nova', 'Circular', 'Helvetica Neue'] as $licensed) {
                $this->assertNotSame($licensed, $display);
                $this->assertNotSame($licensed, $body);
            }
        }
    }

    /** The wizard emits DESIGN TOKENS only — the schema has no place for a
        reference's imagery, logo, or body copy, so they cannot be reproduced. */
    public function test_schema_is_tokens_only_no_imagery_or_copy(): void
    {
        $schema = TokenProfileSchema::schema();
        $props = array_keys($schema['properties']);
        // top-level keys are token concepts only
        $this->assertEqualsCanonicalizing(
            ['name', 'design_read', 'palette', 'typography', 'spacing', 'radius', 'shadow', 'layout'],
            $props,
        );
        // no image/asset/logo/copy fields anywhere in the schema
        $json = json_encode($schema);
        foreach (['image', 'logo', 'photo', 'asset', 'src', 'url', 'content', 'body_text', 'copy'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase("\"{$forbidden}\"", $json, "schema must not carry a '{$forbidden}' field");
        }
    }

    public function test_guardrail_block_carries_the_core_rules(): void
    {
        $b = strtolower(Guardrails::block());
        $this->assertStringContainsString('shift the hues', $b);
        $this->assertStringContainsString('character', $b);       // type by character, not font name
        $this->assertStringContainsString('font name', $b);
        $this->assertStringContainsString('logo', $b);            // no imagery/logo
        $this->assertStringContainsString('trademarked', $b);     // don't clone a brand
    }

    public function test_every_engine_embeds_the_guardrails(): void
    {
        $block = Guardrails::block();
        foreach ([ThemeVisionAnalyzer::class, ThemeNudgeEngine::class, ThemeConversationEngine::class] as $cls) {
            $ref = new \ReflectionClass($cls);
            $m = $ref->getMethod('systemBlocks');
            $m->setAccessible(true);
            $engine = $ref->newInstanceWithoutConstructor();
            $blocks = $m->invoke($engine);
            $text = $blocks[0]['text'] ?? '';
            $this->assertStringContainsString($block, $text, "{$cls} must embed the shared guardrails");
        }
    }
}
