<?php

namespace Tests\Unit\Projection;

use App\Domain\Publishing\Services\RobotsGenerator;
use App\Models\Site;
use Tests\TestCase;

/**
 * Phase 5 — crawler_policy → robots.txt. The policy defaults to permissive, so
 * sites without one produce byte-identical robots.txt as before.
 */
class RobotsCrawlerPolicyTest extends TestCase
{
    private function robots(array $settings): string
    {
        $site = new Site(['slug' => 'demo', 'settings' => $settings]);

        return app(RobotsGenerator::class)->generate($site);
    }

    public function test_default_allows_everything(): void
    {
        $robots = $this->robots([]);
        $this->assertStringContainsString("User-agent: *\nAllow: /", $robots);
        $this->assertStringNotContainsString('User-agent: GPTBot', $robots);
        $this->assertStringNotContainsString('User-agent: PerplexityBot', $robots);
        $this->assertStringNotContainsString('User-agent: Googlebot', $robots);
    }

    public function test_ai_training_deny_blocks_training_bots_only(): void
    {
        $robots = $this->robots(['crawler_policy' => ['ai_training' => 'deny']]);
        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $robots);
        $this->assertStringContainsString("User-agent: Google-Extended\nDisallow: /", $robots);
        $this->assertStringContainsString("User-agent: CCBot\nDisallow: /", $robots);
        // retrieval bots stay allowed
        $this->assertStringNotContainsString('User-agent: PerplexityBot', $robots);
        $this->assertStringNotContainsString('User-agent: ChatGPT-User', $robots);
    }

    public function test_ai_retrieval_deny_blocks_retrieval_bots_only(): void
    {
        $robots = $this->robots(['crawler_policy' => ['ai_retrieval' => 'deny']]);
        $this->assertStringContainsString("User-agent: PerplexityBot\nDisallow: /", $robots);
        $this->assertStringContainsString("User-agent: ChatGPT-User\nDisallow: /", $robots);
        // training bots stay allowed
        $this->assertStringNotContainsString('User-agent: GPTBot', $robots);
        $this->assertStringNotContainsString('User-agent: CCBot', $robots);
    }

    public function test_search_engines_deny_blocks_classic_crawlers(): void
    {
        $robots = $this->robots(['crawler_policy' => ['search_engines' => 'deny']]);
        $this->assertStringContainsString("User-agent: Googlebot\nDisallow: /", $robots);
        $this->assertStringContainsString("User-agent: Bingbot\nDisallow: /", $robots);
    }

    public function test_explicit_optout_and_policy_merge_without_duplication(): void
    {
        $robots = $this->robots([
            'ai_crawlers_disallowed' => ['GPTBot'],
            'crawler_policy' => ['ai_training' => 'deny'],
        ]);
        // GPTBot appears exactly once despite being in both sources.
        $this->assertSame(1, substr_count($robots, "User-agent: GPTBot\n"));
    }
}
