<?php

namespace Tests\Unit\IssueStudio;

use App\Services\IssueStudio\PexelsImageSearch;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PexelsImageSearchTest extends TestCase
{
    public function test_returns_null_without_api_key_and_makes_no_request(): void
    {
        config(['services.pexels.key' => null]);
        Http::fake();

        $this->assertNull(app(PexelsImageSearch::class)->search('mountains'));
        Http::assertNothingSent();
    }

    public function test_returns_null_for_empty_query(): void
    {
        config(['services.pexels.key' => 'test-key']);
        Http::fake();

        $this->assertNull(app(PexelsImageSearch::class)->search('   '));
        Http::assertNothingSent();
    }

    public function test_returns_best_match_and_sends_key_plus_orientation(): void
    {
        config(['services.pexels.key' => 'test-key']);
        Http::fake([
            'api.pexels.com/*' => Http::response([
                'photos' => [[
                    'id' => 42,
                    'photographer' => 'Jane Doe',
                    'photographer_url' => 'https://pexels.com/@jane',
                    'alt' => 'a quiet morning',
                    'src' => [
                        'large2x' => 'https://images.pexels.com/photos/42/x.jpg',
                        'large' => 'https://images.pexels.com/photos/42/l.jpg',
                    ],
                ]],
            ], 200),
        ]);

        $hit = app(PexelsImageSearch::class)->search('quiet morning', 'portrait');

        $this->assertNotNull($hit);
        $this->assertSame('https://images.pexels.com/photos/42/x.jpg', $hit['url']);
        $this->assertSame('Jane Doe', $hit['photographer']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'test-key')
            && str_contains($r->url(), 'orientation=portrait'));
    }

    public function test_returns_null_on_no_results(): void
    {
        config(['services.pexels.key' => 'test-key']);
        Http::fake(['api.pexels.com/*' => Http::response(['photos' => []], 200)]);

        $this->assertNull(app(PexelsImageSearch::class)->search('nothing here at all'));
    }

    public function test_returns_null_on_api_error(): void
    {
        config(['services.pexels.key' => 'test-key']);
        Http::fake(['api.pexels.com/*' => Http::response('rate limited', 429)]);

        $this->assertNull(app(PexelsImageSearch::class)->search('anything'));
    }
}
