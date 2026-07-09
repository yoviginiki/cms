<?php

namespace Tests\Unit\Services;

use App\Domain\Publishing\Services\HtmlMinifier;
use Tests\TestCase;

class HtmlMinifierTest extends TestCase
{
    private function minify(string $html): string
    {
        return app(HtmlMinifier::class)->minify($html);
    }

    public function test_removes_html_comments(): void
    {
        $out = $this->minify('<div>hi<!-- a comment -->there</div>');
        $this->assertStringNotContainsString('a comment', $out);
        $this->assertStringContainsString('hi', $out);
        $this->assertStringContainsString('there', $out);
    }

    public function test_collapses_whitespace(): void
    {
        $out = $this->minify("<div>   \n\n   <span>x</span>   \n   </div>");
        $this->assertStringNotContainsString("\n\n", $out);
        $this->assertStringNotContainsString('     ', $out);
        $this->assertStringContainsString('<span>x</span>', $out);
    }

    public function test_preserves_pre_content(): void
    {
        $pre = "<pre>line1\n    indented\n</pre>";
        $out = $this->minify("<div>  {$pre}  </div>");
        $this->assertStringContainsString("line1\n    indented", $out);
    }

    public function test_preserves_script_content(): void
    {
        $out = $this->minify('<script>var a = {x:1};\nfunction f(){ return a; }</script>');
        $this->assertStringContainsString('function f(){ return a; }', $out);
    }
}
