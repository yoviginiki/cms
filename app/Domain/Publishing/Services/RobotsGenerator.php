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

    /**
     * AI bots that crawl to build TRAINING corpora. Disallowed when the site's
     * crawler_policy.ai_training is 'deny'.
     */
    public const AI_TRAINING_BOTS = [
        'GPTBot',
        'ClaudeBot',
        'anthropic-ai',
        'Google-Extended',
        'Applebot-Extended',
        'CCBot',
        'Bytespider',
        'meta-externalagent',
        'Amazonbot',
        'cohere-ai',
    ];

    /**
     * AI bots that fetch on-demand to answer a live user query (retrieval).
     * Disallowed when crawler_policy.ai_retrieval is 'deny'. The distinction
     * matters: many owners want to be cited in answers but not used for training.
     */
    public const AI_RETRIEVAL_BOTS = [
        'OAI-SearchBot',
        'ChatGPT-User',
        'Claude-User',
        'Claude-SearchBot',
        'PerplexityBot',
        'Perplexity-User',
    ];

    /** Classic search-engine crawlers. Disallowed only when search_engines is 'deny'. */
    public const SEARCH_ENGINES = [
        'Googlebot',
        'Bingbot',
        'Slurp',
        'DuckDuckBot',
        'Baiduspider',
        'YandexBot',
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

        $policy = (array) ($settings['crawler_policy'] ?? []);

        // AI-crawler opt-outs: explicit per-bot list PLUS policy-derived
        // (training / retrieval). Default policy is allow, so a site without a
        // crawler_policy produces byte-identical robots.txt as before.
        $disallowed = array_values(array_intersect((array) ($settings['ai_crawlers_disallowed'] ?? []), self::AI_CRAWLERS));
        if (($policy['ai_training'] ?? 'allow') === 'deny') {
            $disallowed = array_merge($disallowed, self::AI_TRAINING_BOTS);
        }
        if (($policy['ai_retrieval'] ?? 'allow') === 'deny') {
            $disallowed = array_merge($disallowed, self::AI_RETRIEVAL_BOTS);
        }
        foreach (array_values(array_unique($disallowed)) as $bot) {
            $lines[] = '';
            $lines[] = "User-agent: {$bot}";
            $lines[] = 'Disallow: /';
        }

        // Classic search engines (default allow).
        if (($policy['search_engines'] ?? 'allow') === 'deny') {
            foreach (self::SEARCH_ENGINES as $bot) {
                $lines[] = '';
                $lines[] = "User-agent: {$bot}";
                $lines[] = 'Disallow: /';
            }
        }

        $lines[] = '';
        $lines[] = "Sitemap: {$baseUrl}/sitemap.xml";

        return implode("\n", $lines) . "\n";
    }
}
