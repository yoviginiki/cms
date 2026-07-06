<?php

namespace Tests\Unit\Magazine;

use App\Domain\Magazine\Services\DtpRenderService;
use Tests\TestCase;

class DataElementRenderTest extends TestCase
{
    private function renderWith(string $method, array ...$args): string
    {
        $svc = new \ReflectionClass(DtpRenderService::class);
        $m = $svc->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($svc->newInstanceWithoutConstructor(), ...$args);
    }

    public function test_gallery_renders_grid_of_figures(): void
    {
        $html = $this->renderWith('renderGalleryFrame', [
            'galleryImages' => [
                ['src' => '/api/v1/sites/s/assets/a/serve', 'alt' => 'One', 'caption' => 'First'],
                ['src' => 'https://x.test/b.jpg', 'alt' => 'Two', 'caption' => ''],
                ['src' => 'javascript:alert(1)', 'alt' => 'evil'],
            ],
            'galleryColumns' => 3,
        ]);
        $this->assertSame(2, substr_count($html, '<figure'));
        $this->assertStringContainsString('repeat(3,1fr)', $html);
        $this->assertStringContainsString('First', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_empty_gallery_renders_placeholder(): void
    {
        $this->assertStringContainsString('Empty gallery', $this->renderWith('renderGalleryFrame', []));
    }

    public function test_bar_chart_renders_svg_bars_with_labels(): void
    {
        $html = $this->renderWith('renderChartFrame', [
            'chartType' => 'bar',
            'chartData' => [['label' => 'A', 'value' => 10, 'color' => null], ['label' => 'B', 'value' => 20, 'color' => '#ff0000']],
        ]);
        $this->assertSame(2, substr_count($html, '<rect'));
        $this->assertStringContainsString('#ff0000', $html);
        $this->assertStringContainsString('>A</text>', $html);
    }

    public function test_donut_chart_renders_slices_and_hole(): void
    {
        $html = $this->renderWith('renderChartFrame', [
            'chartType' => 'donut',
            'chartData' => [['label' => 'A', 'value' => 60, 'color' => null], ['label' => 'B', 'value' => 40, 'color' => null]],
        ]);
        $this->assertSame(2, substr_count($html, '<path'));
        $this->assertStringContainsString('<circle', $html);
    }

    public function test_stat_renders_number_and_label_with_typography(): void
    {
        $html = $this->renderWith('renderStatFrame', [
            'statValue' => '4,120', 'statPrefix' => '+', 'statSuffix' => '%', 'statLabel' => 'growth',
        ], ['_typography' => ['fontSize' => 72, 'fontFamily' => 'Barlow Condensed']]);
        $this->assertStringContainsString('+4,120%', $html);
        $this->assertStringContainsString('font-size:72px', $html);
        $this->assertStringContainsString('GROWTH', strtoupper($html));
    }

    public function test_progress_clamps_and_renders_bar(): void
    {
        $html = $this->renderWith('renderProgressFrame', [
            'progressValue' => 150, 'progressMax' => 100, 'progressLabel' => 'Done', 'progressColor' => '#10b981',
        ]);
        $this->assertStringContainsString('width:100%', $html);
        $this->assertStringContainsString('Done: 100%', $html);
        $this->assertStringContainsString('#10b981', $html);
    }
}
