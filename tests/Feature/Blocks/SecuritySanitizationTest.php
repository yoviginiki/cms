<?php

namespace Tests\Feature\Blocks;

use App\Domain\Assets\Services\SvgSanitizer;
use App\Domain\Publishing\Services\SanitizationService;
use Tests\TestCase;

/**
 * S7 (SVG upload scrubbing) + S8 (magazine rich text through HTMLPurifier).
 */
class SecuritySanitizationTest extends TestCase
{
    public function test_svg_scripts_handlers_and_external_refs_are_removed(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">
  <script>alert(1)</script>
  <rect width="10" height="10" fill="#E63B2E" onclick="alert(2)"/>
  <use xlink:href="https://evil.example/sprite.svg#x"/>
  <use xlink:href="#local-ok"/>
  <a href="javascript:alert(3)"><circle r="4"/></a>
  <image href="data:text/html,&lt;script&gt;alert(4)&lt;/script&gt;"/>
  <foreignObject><body onload="alert(5)"/></foreignObject>
</svg>
SVG;
        $clean = app(SvgSanitizer::class)->sanitize($svg);

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('data:text/html', $clean);
        $this->assertStringNotContainsString('foreignObject', $clean);
        $this->assertStringNotContainsString('evil.example', $clean);
        // legitimate content survives
        $this->assertStringContainsString('<rect', $clean);
        $this->assertStringContainsString('#E63B2E', $clean);
        $this->assertStringContainsString('#local-ok', $clean);
    }

    public function test_svg_with_entity_expansion_is_rejected(): void
    {
        $svg = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>';

        $this->assertNull(app(SvgSanitizer::class)->sanitize($svg));
    }

    public function test_non_svg_content_is_rejected(): void
    {
        $this->assertNull(app(SvgSanitizer::class)->sanitize('<html><body>nope</body></html>'));
        $this->assertNull(app(SvgSanitizer::class)->sanitize('not xml at all'));
    }

    public function test_magazine_rich_html_purifier_kills_attribute_payloads(): void
    {
        $dirty = '<p onclick="alert(1)">Hi <a href="javascript:alert(2)">link</a>'
            . '<img src="x" onerror="alert(3)"> <strong>bold</strong></p>'
            . '<script>alert(4)</script>';

        $clean = app(SanitizationService::class)->purifyRich($dirty);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('<strong>bold</strong>', $clean);
    }
}
