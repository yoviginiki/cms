<?php

namespace Tests\Unit\Magazine;

use App\Domain\Publishing\Services\SanitizationService;
use Tests\TestCase;

class InlineFigureSanitizeTest extends TestCase
{
    public function test_figure_flow_styles_survive_publish_sanitization(): void
    {
        $svc = app(SanitizationService::class);

        $html = '<p>Before</p>'
            . '<figure style="float:right;width:33%;margin:0 0 8px 12px;">'
            . '<img src="/api/v1/sites/s/assets/a/serve" alt="Peak" style="width:100%;height:auto;display:block;">'
            . '<figcaption style="font-size:10px;opacity:0.7;margin-top:4px;">The north face</figcaption>'
            . '</figure><p>After</p>';

        $out = $svc->purifyMagazine($html);

        $this->assertStringContainsString('float:right', $out);
        $this->assertStringContainsString('width:33%', $out);
        $this->assertStringContainsString('<figcaption', $out);
        $this->assertStringContainsString('The north face', $out);
        $this->assertStringContainsString('font-size:10px', $out);
    }

    public function test_column_span_survives_for_full_width_figures(): void
    {
        $svc = app(SanitizationService::class);
        $out = $svc->purifyMagazine('<figure style="column-span:all;width:100%;"><img src="https://x.test/a.jpg" alt=""></figure>');
        $this->assertStringContainsString('column-span:all', $out);
    }

    public function test_hostile_figure_styles_are_stripped(): void
    {
        $svc = app(SanitizationService::class);
        $out = $svc->purifyMagazine('<figure style="position:fixed;z-index:9999;column-span:evil;"><img src="javascript:alert(1)" alt="x" onerror="alert(1)"></figure>');
        $this->assertStringNotContainsString('position', $out);
        $this->assertStringNotContainsString('column-span', $out);
        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('onerror', $out);
    }
}
