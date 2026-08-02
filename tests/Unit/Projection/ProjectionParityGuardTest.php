<?php

namespace Tests\Unit\Projection;

use App\Domain\Projection\ProjectionParityGuard;
use Tests\TestCase;

class ProjectionParityGuardTest extends TestCase
{
    private function publicWith(array $graph): array
    {
        return ['schema_org' => ['@context' => 'https://schema.org', '@graph' => $graph]];
    }

    public function test_parity_holds_when_text_is_in_html(): void
    {
        $guard = new ProjectionParityGuard();
        $public = $this->publicWith([
            ['@type' => 'ImageObject', 'name' => 'A cat', 'stillopress:blockAddress' => 'i1'],
        ]);

        $missing = $guard->check($public, '<figure><img src="x.jpg" alt="A cat"><figcaption>A cat</figcaption></figure>');

        $this->assertSame([], $missing);
    }

    public function test_parity_fails_and_points_at_the_field(): void
    {
        $guard = new ProjectionParityGuard();
        $public = $this->publicWith([
            ['@type' => 'ImageObject', 'name' => 'Invisible caption', 'stillopress:blockAddress' => 'i1'],
        ]);

        $missing = $guard->check($public, '<p>Completely different visible content.</p>');

        $this->assertCount(1, $missing);
        $this->assertSame('name', $missing[0]['key']);
        $this->assertSame('i1', $missing[0]['address']);
    }

    public function test_urls_and_structural_keys_are_ignored(): void
    {
        $guard = new ProjectionParityGuard();
        $public = $this->publicWith([
            ['@type' => 'ImageObject', 'contentUrl' => '/assets/9.jpg', 'image' => 'asset-9', 'stillopress:blockAddress' => 'i1'],
        ]);

        // No prose keys → nothing to verify → parity holds regardless of HTML.
        $this->assertSame([], $guard->check($public, '<p>nothing here</p>'));
    }

    public function test_facts_in_attributes_satisfy_parity(): void
    {
        // An image alt lives in an attribute, not a text node, but is still a
        // fact present in the rendered HTML.
        $guard = new ProjectionParityGuard();
        $public = $this->publicWith([
            ['@type' => 'ImageObject', 'name' => 'A friendly cat', 'stillopress:blockAddress' => 'i1'],
        ]);

        $this->assertSame([], $guard->check($public, '<img src="c.jpg" alt="A friendly cat">'));
    }

    public function test_matching_is_case_and_whitespace_insensitive(): void
    {
        $guard = new ProjectionParityGuard();
        $public = $this->publicWith([
            ['@type' => 'ImageObject', 'caption' => 'Hello   World', 'stillopress:blockAddress' => 'i1'],
        ]);

        $this->assertSame([], $guard->check($public, '<p>hello world</p>'));
    }
}
