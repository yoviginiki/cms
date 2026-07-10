<?php

namespace Tests\Feature\ThemeWizard;

use App\Services\AI\AnthropicClient;
use App\Services\AI\SchemaRepairLoop;
use App\Services\IssueStudio\TokenBudget;
use App\Services\ThemeWizard\ReferenceCaptureService;
use App\Services\ThemeWizard\ReferenceThemeService;
use App\Services\ThemeWizard\ThemeVisionAnalyzer;
use App\Services\ThemeWizard\TokenProfileCompiler;
use App\Services\ThemeWizard\TokenProfileValidator;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class ReferencePipelineTest extends TestCase
{
    private function profileJson(bool $valid = true): string
    {
        $p = [
            'name' => 'Harbor',
            'design_read' => 'Calm maritime editorial — pale sky, a single ink-blue mark, airy columns.',
            'palette' => [
                'brand' => '#1B4965', 'accent' => '#BC4B51',
                'background' => '#F7F9FB', 'surface' => '#EDF1F5',
                'text' => '#2B3440', 'heading' => '#14202B', 'muted' => '#7C8996', 'border' => '#DCE3EA',
            ],
            'typography' => [
                'display_character' => 'neutral grotesque sans',
                'body_character' => 'humanist reading serif',
                'scale' => 'balanced', 'heading_weight' => 600,
            ],
            'spacing' => 'airy', 'radius' => 'sharp', 'shadow' => 'none', 'layout' => 'magazine',
        ];
        if (!$valid) $p['palette']['accent'] = $p['palette']['brand']; // fails distinctness
        return json_encode($p);
    }

    /** A stand-in AnthropicClient that returns scripted responses, no network. */
    private function fakeClient(array $texts): AnthropicClient
    {
        return new class($texts) extends AnthropicClient {
            public int $calls = 0;
            public function __construct(private array $texts) {}
            public function complete(string $model, array $sys, array $msgs, int $max = 4096, ?array $schema = null): array
            {
                $text = $this->texts[$this->calls] ?? end($this->texts);
                $this->calls++;
                return ['text' => $text, 'usage' => ['input' => 500, 'output' => 300, 'cache_write' => 0, 'cache_read' => 0, 'model' => $model]];
            }
        };
    }

    private function analyzer(AnthropicClient $client): ThemeVisionAnalyzer
    {
        return new ThemeVisionAnalyzer($client, new TokenProfileValidator(), new SchemaRepairLoop(), app(TokenBudget::class));
    }

    // ── SSRF guard ──

    public function test_ssrf_guard_blocks_local_and_private_targets(): void
    {
        $svc = new ReferenceCaptureService();
        foreach ([
            'http://localhost/admin',
            'http://127.0.0.1:8000',
            'http://169.254.169.254/latest/meta-data/', // cloud metadata
            'http://10.0.0.5',
            'http://192.168.1.1',
            'file:///etc/passwd',
            'ftp://example.com',
            'http://user:pass@example.com',
            'not a url',
        ] as $bad) {
            try {
                $svc->assertPublicHttpUrl($bad);
                $this->fail("should have blocked: {$bad}");
            } catch (RuntimeException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_ssrf_guard_allows_a_public_https_url(): void
    {
        $svc = new ReferenceCaptureService();
        // resolves to public IPs
        $this->assertSame('https://example.com', $svc->assertPublicHttpUrl('https://example.com'));
    }

    // ── upload validation ──

    public function test_upload_rejects_non_image(): void
    {
        $svc = new ReferenceCaptureService();
        $this->expectException(RuntimeException::class);
        $svc->fromUpload(UploadedFile::fake()->createWithContent('notes.txt', 'hello'));
    }

    public function test_upload_accepts_a_png(): void
    {
        $svc = new ReferenceCaptureService();
        $png = UploadedFile::fake()->image('ref.png', 400, 300);
        $out = $svc->fromUpload($png);
        $this->assertSame('image/png', $out['media_type']);
        $this->assertNotEmpty($out['data']);
        $this->assertNotFalse(base64_decode($out['data'], true));
    }

    // ── analyzer (fake AI) ──

    public function test_analyzer_returns_validated_profile_and_charges_budget(): void
    {
        $this->setTenantScope($this->owner);
        $client = $this->fakeClient([$this->profileJson()]);
        $out = $this->analyzer($client)->analyze($this->tenant->id, 'ZmFrZQ==', 'image/png');

        $this->assertSame('Harbor', $out['profile']['name']);
        $this->assertSame('magazine', $out['profile']['layout']);
        $this->assertCount(1, $out['usages']);
        // budget recorded input+output
        $this->assertSame(800, (int) $this->tenant->fresh()->monthly_tokens_used);
    }

    public function test_analyzer_repairs_an_invalid_first_response(): void
    {
        $this->setTenantScope($this->owner);
        $client = $this->fakeClient([$this->profileJson(false), $this->profileJson(true)]);
        $out = $this->analyzer($client)->analyze($this->tenant->id, 'ZmFrZQ==', 'image/png');
        $this->assertSame('Harbor', $out['profile']['name']);
        $this->assertSame(2, $client->calls);
        $this->assertCount(2, $out['usages']); // both attempts charged
    }

    public function test_analyzer_respects_budget_exhaustion(): void
    {
        $this->setTenantScope($this->owner);
        // budget columns aren't Eloquent-fillable; write directly (as TokenBudget
        // reads). Future reset date so the counter is not auto-rolled to 0.
        \Illuminate\Support\Facades\DB::table('tenants')->where('id', $this->tenant->id)->update([
            'monthly_token_budget' => 100,
            'monthly_tokens_used' => 100,
            'token_usage_reset_at' => now()->addMonth(),
        ]);
        $this->expectException(RuntimeException::class);
        $this->analyzer($this->fakeClient([$this->profileJson()]))->analyze($this->tenant->id, 'ZmFrZQ==', 'image/png');
    }

    // ── orchestrator ──

    public function test_reference_service_compiles_a_theme_from_an_upload(): void
    {
        $this->setTenantScope($this->owner);
        $svc = new ReferenceThemeService(
            new ReferenceCaptureService(),
            $this->analyzer($this->fakeClient([$this->profileJson()])),
            new TokenProfileCompiler(),
        );
        $out = $svc->fromUpload($this->tenant->id, UploadedFile::fake()->image('ref.png', 400, 300));
        $this->assertSame('magazine', $out['compiled']['document']['layout']['style']);
        $this->assertStringContainsString('Harbor', $out['compiled']['name']);
        // compiled document is a resolvable theme (has semantic brand)
        $this->assertSame('#1B4965', $out['compiled']['document']['semantic']['color']['brand']['$value']);
    }
}
