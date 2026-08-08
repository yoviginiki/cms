<?php

namespace Tests\Unit\InlineEdit;

use App\Domain\InlineEdit\Services\InlineEditService;
use App\Models\Block;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * DB-free proof of the Phase 3 write rules: unknown field path → 422,
 * optimistic-lock mismatch → 409, shared-entity block → 403, and reuse of the
 * existing sanitizer. Boots the framework by hand (no RefreshDatabase, no
 * queries) — the service is pure, so no database is touched.
 */
final class InlineEditServiceTest extends TestCase
{
    private static \Illuminate\Foundation\Application $app;
    private InlineEditService $svc;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/vendor/autoload.php';
        self::$app = require $root . '/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();
    }

    protected function setUp(): void
    {
        $this->svc = app(InlineEditService::class);
    }

    private function statusOf(callable $fn): int
    {
        try {
            $fn();
        } catch (HttpException $e) {
            return $e->getStatusCode();
        }
        return 0; // no exception
    }

    // ---- unknown / reserved field paths → 422 ------------------------------

    public function test_unknown_field_path_is_rejected_422(): void
    {
        $block = new Block(['type' => 'heading', 'data' => ['text' => 'Hi']]);
        $this->assertSame(422, $this->statusOf(fn () => $this->svc->sanitizeField($block, 'bogus', 'x')));
    }

    public function test_reserved_key_is_rejected_422(): void
    {
        $block = new Block(['type' => 'heading', 'data' => ['text' => 'Hi']]);
        $this->assertSame(422, $this->statusOf(fn () => $this->svc->sanitizeField($block, '__style', ['x' => 1])));
    }

    public function test_deep_dotted_path_is_rejected_422(): void
    {
        $block = new Block(['type' => 'heading', 'data' => ['text' => 'Hi']]);
        $this->assertSame(422, $this->statusOf(fn () => $this->svc->sanitizeField($block, 'text.inner', 'x')));
    }

    public function test_schema_invalid_value_is_rejected_422(): void
    {
        // heading.level must be h1..h6
        $block = new Block(['type' => 'heading', 'data' => ['text' => 'Hi']]);
        $this->assertSame(422, $this->statusOf(fn () => $this->svc->sanitizeField($block, 'level', 'h9')));
    }

    // ---- valid fields sanitize through the existing pipeline ---------------

    public function test_heading_text_is_plain_text_sanitized(): void
    {
        $block = new Block(['type' => 'heading', 'data' => ['text' => 'Hi']]);
        $out = $this->svc->sanitizeField($block, 'text', 'Hello <b>bold</b>');
        $this->assertStringNotContainsString('<b>', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function test_richtext_content_keeps_allowed_tags(): void
    {
        $block = new Block(['type' => 'rich-text', 'data' => ['content' => '<p>x</p>']]);
        $out = $this->svc->sanitizeField($block, 'content', '<p>ok <strong>keep</strong><script>bad()</script></p>');
        $this->assertStringContainsString('<strong>keep</strong>', $out);
        $this->assertStringNotContainsString('<script', $out);
    }

    public function test_image_url_valid_value_passes(): void
    {
        $block = new Block(['type' => 'image', 'data' => ['url' => 'https://a/b.jpg']]);
        $out = $this->svc->sanitizeField($block, 'url', 'https://cdn.example/pic.jpg');
        $this->assertSame('https://cdn.example/pic.jpg', $out);
    }

    // ---- shared-entity block → 403 -----------------------------------------

    public function test_shared_entity_block_is_forbidden_403(): void
    {
        foreach (['slider_ref', 'global_ref', 'menu'] as $type) {
            $block = new Block(['type' => $type, 'data' => []]);
            $this->assertSame(403, $this->statusOf(fn () => $this->svc->assertPatchable($block)), $type);
        }
    }

    public function test_normal_block_is_patchable(): void
    {
        $block = new Block(['type' => 'heading', 'data' => []]);
        $this->assertSame(0, $this->statusOf(fn () => $this->svc->assertPatchable($block)));
    }

    // ---- optimistic lock → 409 ---------------------------------------------

    public function test_hash_mismatch_is_conflict_409(): void
    {
        $block = new Block(['type' => 'heading', 'data' => ['text' => 'current']]);
        $this->assertSame(409, $this->statusOf(fn () => $this->svc->assertHashMatches($block, 'stale-hash-from-session')));
    }

    public function test_matching_hash_passes(): void
    {
        $block = new Block(['type' => 'heading', 'data' => ['text' => 'current']]);
        $hash = $this->svc->blockHash($block);
        $this->assertSame(0, $this->statusOf(fn () => $this->svc->assertHashMatches($block, $hash)));
    }

    public function test_null_hash_skips_lock(): void
    {
        $block = new Block(['type' => 'heading', 'data' => ['text' => 'current']]);
        $this->assertSame(0, $this->statusOf(fn () => $this->svc->assertHashMatches($block, null)));
    }
}
