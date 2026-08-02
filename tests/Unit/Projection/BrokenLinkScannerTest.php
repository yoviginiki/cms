<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\Health\BrokenLinkScanner;
use Tests\TestCase;

class BrokenLinkScannerTest extends TestCase
{
    private function projectionWithLinks(array $links): array
    {
        return ['inventory' => ['outbound_links' => $links]];
    }

    public function test_detects_broken_internal_link(): void
    {
        $pages = [
            '/a/' => $this->projectionWithLinks([
                ['url' => '/b/', 'address' => 'x#content', 'internal' => true],       // ok
                ['url' => '/missing/', 'address' => 'y#content', 'internal' => true],  // broken
                ['url' => 'https://ext/', 'address' => 'z#content', 'internal' => false], // ignored
            ]),
            '/b/' => $this->projectionWithLinks([]),
        ];

        $broken = (new BrokenLinkScanner())->scan($pages);

        $this->assertCount(1, $broken);
        $this->assertSame('/a/', $broken[0]['source']);
        $this->assertSame('/missing/', $broken[0]['target']);
        $this->assertSame('y#content', $broken[0]['address']);
    }

    public function test_trailing_slash_and_fragment_normalization(): void
    {
        $pages = [
            '/about/' => $this->projectionWithLinks([
                ['url' => '/about', 'address' => 'a#c', 'internal' => true],        // same page, no slash
                ['url' => '/team#lead', 'address' => 'b#c', 'internal' => true],    // fragment on real page
                ['url' => '#top', 'address' => 'c#c', 'internal' => true],          // pure anchor
            ]),
            '/team/' => $this->projectionWithLinks([]),
        ];

        $this->assertSame([], (new BrokenLinkScanner())->scan($pages));
    }

    public function test_clean_site_reports_nothing(): void
    {
        $pages = [
            '/' => $this->projectionWithLinks([['url' => '/a/', 'address' => 'h#c', 'internal' => true]]),
            '/a/' => $this->projectionWithLinks([]),
        ];

        $this->assertSame([], (new BrokenLinkScanner())->scan($pages));
    }
}
