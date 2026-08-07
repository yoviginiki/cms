<?php

namespace Tests\Unit\Modules;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Blade render snapshots for the culture blocks, including null-field variants.
 * Output is flat static HTML — no <script>, no JS.
 */
class CultureBlockRenderTest extends TestCase
{
    private function render(string $view, array $data, string $children = ''): string
    {
        return View::make("blocks.{$view}", ['data' => $data, 'children' => $children])->render();
    }

    public function test_event_card_full_renders_all_parts(): void
    {
        $html = $this->render('event-card', [
            'title' => 'Jazz night',
            'start_at' => '2026-08-14T20:00:00',
            'end_at' => '2026-08-14T22:30:00',
            'city' => 'Sofia',
            'venue' => 'NDK',
            'short_description' => 'A great show.',
            'is_free' => true,
            'ticket_url' => 'https://tickets.example/jazz',
            'official_url' => 'https://ndk.bg/jazz',
        ]);

        $this->assertStringContainsString('Jazz night', $html);
        $this->assertStringContainsString('NDK, Sofia', $html);
        $this->assertStringContainsString('Free entry', $html);
        $this->assertStringContainsString('href="https://tickets.example/jazz"', $html);
        $this->assertStringContainsString('href="https://ndk.bg/jazz"', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_event_card_missing_urls_render_no_broken_anchors(): void
    {
        $html = $this->render('event-card', [
            'title' => 'Free talk',
            'city' => 'Plovdiv',
            // no ticket_url, no official_url, no start_at/end_at
        ]);

        $this->assertStringContainsString('Free talk', $html);
        $this->assertStringNotContainsString('<a ', $html);        // no anchors at all
        $this->assertStringNotContainsString('<time', $html);      // no dates
        $this->assertStringNotContainsString('Tickets', $html);
    }

    public function test_event_card_empty_title_shows_nothing_broken(): void
    {
        $html = $this->render('event-card', []);
        // Renders an <article> shell without throwing; no title heading.
        $this->assertStringContainsString('event-card', $html);
        $this->assertStringNotContainsString('<h3', $html);
    }

    public function test_bulletin_section_with_title_and_children(): void
    {
        $html = $this->render('bulletin-section', ['title' => 'Concerts'], '<article>child</article>');

        $this->assertStringContainsString('<h2 class="bulletin-section__title">Concerts</h2>', $html);
        $this->assertStringContainsString('<article>child</article>', $html);
    }

    public function test_bulletin_section_without_title_omits_heading(): void
    {
        $html = $this->render('bulletin-section', [], '');
        $this->assertStringNotContainsString('<h2', $html);
        $this->assertStringContainsString('bulletin-section__events', $html);
    }
}
