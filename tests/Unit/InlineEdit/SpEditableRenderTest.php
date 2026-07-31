<?php

namespace Tests\Unit\InlineEdit;

use App\Domain\Publishing\Rendering\RenderContext;
use App\Domain\Publishing\Rendering\RenderMode;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1 guarantee test — the inline-edit layer must be invisible on the
 * publish path.
 *
 * This test is deliberately DB-free: it boots the framework by hand (no
 * RefreshDatabase, no tenant/user setup) and only renders Blade partials, so
 * it never opens a database connection. It proves three things for the three
 * pilot blocks (heading, rich-text, image):
 *
 *   1. RenderMode::Publish output is byte-for-byte identical to a pinned
 *      snapshot taken before the sp_editable() wiring was added.
 *   2. Publish output carries no data-sp-* attribute and no injected
 *      <script> / inline style from this layer.
 *   3. RenderMode::Edit emits the exact addressing contract on the editable
 *      element: data-sp-block / data-sp-field / data-sp-type.
 */
final class SpEditableRenderTest extends TestCase
{
    private static \Illuminate\Foundation\Application $app;

    /** Fixture data mirrors database/seeders/DemoBlocksPageSeeder.php. */
    private const DATA = [
        'heading' => [
            'text' => 'Section 1: Typography', 'level' => 'h1', 'color' => '#1a1a2e',
            'fontSize' => '3rem', 'fontWeight' => '800', 'lineHeight' => '', 'letterSpacing' => '',
            'textTransform' => '', 'textAlign' => 'center',
        ],
        'rich-text' => [
            'content' => '<h3>Rich Text Block</h3><p>Typography is the art of arranging type.</p><ul><li>One</li><li>Two</li></ul>',
        ],
        'image' => [
            'assetId' => null,
            'url' => 'https://images.unsplash.com/photo-mountain.jpg',
            'alt' => 'Mountain landscape at dawn',
            'caption' => 'Dolomites, Italy',
            'size' => 'full',
        ],
        'paragraph' => ['content' => '<p>Параграф с <strong>удебелен</strong> текст.</p>'],
        'text' => ['content' => '<p>Текстов блок на живо.</p>'],
        'pullquote' => ['text' => 'Цитат на живо.', 'attribution' => 'Автор', 'style' => 'large-text'],
        'button' => ['text' => 'Натисни ме', 'url' => 'https://example.com'],
        'hero' => ['title' => 'Геройско заглавие', 'subtitle' => 'Кратко подзаглавие'],
        'ctabanner' => ['heading' => 'Готови ли сте?'],
        'sidenote' => ['content' => 'Странична бележка на живо.', 'side' => 'right'],
    ];

    private const BLOCK_ID = '11111111-2222-3333-4444-555555555555';

    /** field path + type asserted in Edit mode, per pilot block. */
    private const FIELD = [
        'heading' => ['text', 'text'],
        'rich-text' => ['content', 'richtext'],
        'image' => ['url', 'image'],
        'paragraph' => ['content', 'richtext'],
        'text' => ['content', 'richtext'],
        'pullquote' => ['text', 'text'],
        'button' => ['text', 'text'],
        'hero' => ['title', 'text'],
        'ctabanner' => ['heading', 'text'],
        'sidenote' => ['content', 'text'],
    ];

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 3);
        require_once $root . '/vendor/autoload.php';
        self::$app = require $root . '/bootstrap/app.php';
        self::$app->make(Kernel::class)->bootstrap();
    }

    protected function setUp(): void
    {
        // Every test starts from the safe default.
        app(RenderContext::class)->set(RenderMode::Publish);
    }

    private function render(string $type): string
    {
        return View::make("blocks.$type", [
            'data' => self::DATA[$type],
            'children' => '', 'childrenArray' => [], 'site' => null,
            'blockStyle' => [], 'blockAnimation' => [], 'blockAdvanced' => [], 'blockResponsive' => [],
            '__locale' => 'bg',
            '__blockId' => self::BLOCK_ID,
            '__blockType' => $type,
        ])->render();
    }

    public static function pilotBlocks(): array
    {
        return [
            'heading' => ['heading'],
            'rich-text' => ['rich-text'],
            'image' => ['image'],
            'paragraph' => ['paragraph'],
            'text' => ['text'],
            'pullquote' => ['pullquote'],
            'button' => ['button'],
            'hero' => ['hero'],
            'ctabanner' => ['ctabanner'],
            'sidenote' => ['sidenote'],
        ];
    }

    /**
     * @dataProvider pilotBlocks
     */
    public function test_publish_output_is_byte_identical_to_pinned_snapshot(string $type): void
    {
        $expected = file_get_contents(__DIR__ . "/snapshots/$type.publish.html");

        app(RenderContext::class)->set(RenderMode::Publish);
        $actual = $this->render($type);

        $this->assertSame(
            $expected,
            $actual,
            "Publish render of '$type' drifted from the pinned snapshot — the inline-edit layer must not change published bytes.",
        );
    }

    /**
     * @dataProvider pilotBlocks
     */
    public function test_publish_emits_no_inline_edit_artifacts(string $type): void
    {
        app(RenderContext::class)->set(RenderMode::Publish);
        $html = $this->render($type);

        $this->assertStringNotContainsString('data-sp-', $html, "'$type' leaked a data-sp-* attribute on the publish path.");
        $this->assertStringNotContainsString('<script', $html, "'$type' injected a <script> on the publish path.");
    }

    /**
     * @dataProvider pilotBlocks
     */
    public function test_edit_mode_emits_addressing_contract(string $type): void
    {
        app(RenderContext::class)->set(RenderMode::Edit);
        $html = $this->render($type);

        [$field, $fieldType] = self::FIELD[$type];

        $this->assertStringContainsString('data-sp-block="' . self::BLOCK_ID . '"', $html);
        $this->assertStringContainsString('data-sp-field="' . $field . '"', $html);
        $this->assertStringContainsString('data-sp-type="' . $fieldType . '"', $html);
    }

    /**
     * The edit-mode attributes must add exactly what publish lacks and nothing
     * more: stripping every data-sp-* attribute must return the byte-identical
     * publish snapshot.
     *
     * @dataProvider pilotBlocks
     */
    public function test_edit_mode_is_publish_plus_only_sp_attributes(string $type): void
    {
        app(RenderContext::class)->set(RenderMode::Edit);
        $editHtml = $this->render($type);

        $stripped = preg_replace('/ data-sp-[a-z]+="[^"]*"/', '', $editHtml);
        $expected = file_get_contents(__DIR__ . "/snapshots/$type.publish.html");

        $this->assertSame(
            $expected,
            $stripped,
            "Edit mode for '$type' changed more than just adding data-sp-* attributes.",
        );
    }
}
