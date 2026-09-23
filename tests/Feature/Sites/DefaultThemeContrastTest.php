<?php

namespace Tests\Feature\Sites;

use App\Domain\Sites\Services\SiteService;
use Tests\TestCase;

/**
 * H04 — Lighthouse on a freshly created site flagged the primary button:
 * white on the seeded #3b82f6 is 3.68:1. The seeded primary must reach
 * WCAG AA (4.5:1) against white text.
 */
class DefaultThemeContrastTest extends TestCase
{
    private static function luminance(string $hex): float
    {
        $c = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
        $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public function test_new_site_primary_passes_aa_with_white_text(): void
    {
        $this->setTenantScope($this->owner);
        $site = app(SiteService::class)->createSite(['name' => 'Contrast'], $this->tenant);
        $theme = $site->fresh()->theme;

        foreach ([$theme->config['colors']['primary'], $theme->document['primitive']['color']['blue']['500']['$value']] as $hex) {
            $ratio = 1.05 / (self::luminance($hex) + 0.05);
            $this->assertGreaterThanOrEqual(4.5, $ratio, "{$hex} is {$ratio}:1 on white");
        }
    }
}
