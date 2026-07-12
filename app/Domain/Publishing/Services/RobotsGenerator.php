<?php
namespace App\Domain\Publishing\Services;

use App\Models\Site;

class RobotsGenerator
{
    /**
     * Known AI crawlers (Track F4). Default policy is ALLOW — being cited is
     * distribution; owners opt out per bot via settings.ai_crawlers_disallowed.
     * Keep this list maintainable: adding a name here is the whole change.
     */
    public const AI_CRAWLERS = [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-User',
        'Claude-SearchBot',
        'anthropic-ai',
        'PerplexityBot',
        'Perplexity-User',
        'Google-Extended',
        'Applebot-Extended',
        'CCBot',
        'Bytespider',
        'meta-externalagent',
        'Amazonbot',
        'cohere-ai',
    ];

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
        ];

        // Per-bot AI-crawler opt-outs (unknown names ignored)
        $disallowed = array_intersect((array) ($settings['ai_crawlers_disallowed'] ?? []), self::AI_CRAWLERS);
        foreach ($disallowed as $bot) {
            $lines[] = '';
            $lines[] = "User-agent: {$bot}";
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = "Sitemap: {$baseUrl}/sitemap.xml";

        return implode("\n", $lines) . "\n";
    }
}
