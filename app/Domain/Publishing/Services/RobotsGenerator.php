<?php
namespace App\Domain\Publishing\Services;

use App\Models\Site;

class RobotsGenerator
{
    public function generate(Site $site): string
    {
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
        $settings = $site->settings ?? [];

        if (!empty($settings['robots'])) {
            return $settings['robots'];
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            '',
            "Sitemap: {$baseUrl}/sitemap.xml",
        ];

        return implode("\n", $lines) . "\n";
    }
}
