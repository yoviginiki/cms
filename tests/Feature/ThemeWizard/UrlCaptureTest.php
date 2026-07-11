<?php

namespace Tests\Feature\ThemeWizard;

use App\Jobs\ThemeWizard\CaptureReferenceJob;
use App\Models\Site;
use App\Models\ThemeWizard\WizardSession;
use App\Services\AI\AnthropicClient;
use App\Services\ThemeWizard\ReferenceCaptureService;
use App\Services\ThemeWizard\ThemeWizardService;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;

/**
 * The "from URL" path captures a screenshot with Playwright, which needs
 * proc_open — disabled in the web pool. So it runs on the queue worker: the
 * request creates a `capturing` session + dispatches CaptureReferenceJob, and
 * the worker fills it (→ drafting) or records a failure (→ capture_failed).
 */
class UrlCaptureTest extends TestCase
{
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setTenantScope($this->owner);
        $this->site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        config(['cms.ai.api_key' => 'test-key']);
    }

    private function profileJson(string $name = 'Harbor'): string
    {
        return json_encode([
            'name' => $name,
            'design_read' => 'Calm maritime editorial.',
            'palette' => [
                'brand' => '#1B4965', 'accent' => '#BC4B51',
                'background' => '#F7F9FB', 'surface' => '#EDF1F5',
                'text' => '#2B3440', 'heading' => '#14202B', 'muted' => '#7C8996', 'border' => '#DCE3EA',
            ],
            'typography' => [
                'display_character' => 'neutral grotesque sans', 'body_character' => 'humanist reading serif',
                'scale' => 'balanced', 'heading_weight' => 600,
            ],
            'spacing' => 'airy', 'radius' => 'sharp', 'shadow' => 'none', 'layout' => 'magazine',
        ]);
    }

    private function fakeAi(string $text): void
    {
        $fake = new class($text) extends AnthropicClient {
            public function __construct(private string $text) {}
            public function complete(string $model, array $sys, array $msgs, int $max = 4096, ?array $schema = null): array
            {
                return ['text' => $this->text, 'usage' => ['input' => 400, 'output' => 250, 'cache_write' => 0, 'cache_read' => 0, 'model' => $model]];
            }
        };
        $this->app->instance(AnthropicClient::class, $fake);
    }

    /** Fake the screenshot so no node/chromium is spawned in the test. */
    private function fakeCapture(?callable $onUrl = null): void
    {
        $svc = new class($onUrl) extends ReferenceCaptureService {
            public function __construct(private $onUrl) {}
            public function fromUrl(string $url): array
            {
                if ($this->onUrl) ($this->onUrl)($url);
                // 1×1 transparent PNG
                return ['data' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'media_type' => 'image/png'];
            }
        };
        $this->app->instance(ReferenceCaptureService::class, $svc);
    }

    public function test_from_url_creates_a_capturing_session_and_dispatches_the_job(): void
    {
        Bus::fake();

        $resp = $this->actingAsOwner()
            ->postJson("/api/v1/sites/{$this->site->id}/theme-wizard/sessions/from-url", [
                'url' => 'https://example.com', 'hint' => 'warmer',
            ])
            ->assertStatus(201);

        $resp->assertJsonPath('data.status', 'capturing')
            ->assertJsonPath('data.source', 'reference')
            ->assertJsonPath('data.reference_url', 'https://example.com');
        // no candidate yet — the worker fills it
        $this->assertNull($resp->json('data.candidate.name'));
        // opening user line is present; the assistant design-read comes later
        $this->assertCount(1, $resp->json('data.transcript'));

        $sid = $resp->json('data.id');
        Bus::assertDispatched(CaptureReferenceJob::class, fn ($job) => $job->sessionId === $sid && $job->hint === 'warmer');
    }

    public function test_worker_completes_capture_into_a_drafting_candidate(): void
    {
        $this->fakeAi($this->profileJson());
        $captured = null;
        $this->fakeCapture(function ($url) use (&$captured) { $captured = $url; });

        // start (job is not run inline in tests — drive completeUrlCapture directly,
        // exactly as CaptureReferenceJob::handle does on the worker)
        $service = $this->app->make(ThemeWizardService::class);
        $session = $service->startFromUrl($this->site, $this->owner, 'https://example.com', 'warmer');
        $this->assertSame('capturing', $session->status);

        $service->completeUrlCapture($session->refresh(), 'warmer');

        $session->refresh();
        $this->assertSame('drafting', $session->status);
        $this->assertSame('https://example.com', $captured);
        $this->assertSame('Harbor', $session->title);
        $this->assertSame('magazine', $session->candidate['document']['layout']['style']);
        $this->assertNull($session->error);
        $this->assertCount(2, $session->transcript); // opening + design read
    }

    public function test_worker_records_a_clean_failure_when_capture_throws(): void
    {
        $svc = new class extends ReferenceCaptureService {
            public function fromUrl(string $url): array
            {
                throw new RuntimeException('Could not capture that site — check the URL is reachable and public.');
            }
        };
        $this->app->instance(ReferenceCaptureService::class, $svc);

        $service = $this->app->make(ThemeWizardService::class);
        $session = $service->startFromUrl($this->site, $this->owner, 'https://unreachable.invalid');

        $service->completeUrlCapture($session->refresh(), null);

        $session->refresh();
        $this->assertSame('capture_failed', $session->status);
        $this->assertStringContainsString('Could not capture', $session->error);
    }

    public function test_complete_is_idempotent_once_resolved(): void
    {
        $this->fakeAi($this->profileJson());
        $this->fakeCapture();
        $service = $this->app->make(ThemeWizardService::class);

        $session = $service->startFromUrl($this->site, $this->owner, 'https://example.com');
        $service->completeUrlCapture($session->refresh(), null);
        $this->assertSame('drafting', $session->refresh()->status);

        // a second (duplicate/retried) run must not re-process or clobber
        $service->completeUrlCapture($session->refresh(), null);
        $this->assertSame('drafting', $session->refresh()->status);
    }
}
