<?php

namespace Database\Seeders;

use App\Support\Seeding\SystemRecordSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starter section packs (Builder P5 · owned by the Theme track). Ships a set of
 * ready-made SYSTEM Library sections (`block_templates`, site_id = NULL,
 * is_system = true) so every tenant can drop a polished section into a blank
 * page and just change the words — the blank-page killer.
 *
 * THEME-AGNOSTIC BY DESIGN. Every section is built from design tokens
 * (`var(--color-*)` / `var(--font-*)`), so one catalog adapts to all five
 * first-party themes (enso · journal · ledger · atelier · hearth) instead of
 * shipping five hand-maintained copies. Rich visuals use `html-embed` (rendered
 * raw at publish, exactly how the flagship marketing site is built); the token
 * refs re-colour per active theme.
 *
 * Idempotent: upserts by (slug, site_id NULL). Runs standalone via
 *   php artisan db:seed --class=Database\\Seeders\\StarterSectionSeeder
 */
class StarterSectionSeeder extends Seeder
{
    private int $rowOrder = 0;
    private int $colOrder = 0;
    private int $blockOrder = 0;

    public function run(): void
    {
        $catalog = $this->catalog();

        SystemRecordSeeder::withRlsDisabled('block_templates', function () use ($catalog) {
            foreach ($catalog as $item) {
                $this->upsert($item);
            }
        });

        $this->command?->info('Seeded ' . count($catalog) . ' starter sections.');
    }

    private function upsert(array $item): void
    {
        $existing = DB::table('block_templates')
            ->whereNull('site_id')->where('is_system', true)
            ->where('slug', $item['slug'])->first();

        $row = [
            'name' => $item['name'],
            'category' => $item['category'],
            'kind' => 'section',
            'tags' => json_encode(array_values(array_unique(array_merge(['starter'], $item['tags'])))),
            'description' => $item['description'],
            'blocks_data' => json_encode($item['blocks']),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('block_templates')->where('id', $existing->id)->update($row);
        } else {
            DB::table('block_templates')->insert($row + [
                'id' => (string) Str::uuid(),
                'site_id' => null,
                'slug' => $item['slug'],
                'is_system' => true,
                'preview_image' => null,
                'created_at' => now(),
            ]);
        }
    }

    /** @return array<int,array{slug:string,name:string,category:string,tags:array,description:string,blocks:array}> */
    private function catalog(): array
    {
        return [
            $this->heroCentered(),
            $this->heroSplit(),
            $this->featuresTrio(),
            $this->callToAction(),
            $this->statsBand(),
            $this->testimonial(),
            $this->contentImage(),
            $this->logoStrip(),
        ];
    }

    /* ─────────────────────────────── sections ─────────────────────────────── */

    private function heroCentered(): array
    {
        $html = <<<'HTML'
<div style="max-width:820px;margin:0 auto;padding:clamp(3rem,9vh,6rem) 1.25rem;text-align:center">
  <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.24em;font-weight:600;font-size:.76rem;color:var(--color-text-muted);margin:0 0 1.6rem">Now in open beta</p>
  <h1 style="font-family:var(--font-heading);font-weight:600;font-size:clamp(2.6rem,6vw,4.6rem);line-height:1.02;letter-spacing:-.02em;color:var(--color-heading);margin:0 0 1.4rem">Build something worth keeping.</h1>
  <p style="font-size:clamp(1.05rem,1.4vw,1.3rem);line-height:1.6;color:var(--color-text-muted);max-width:46ch;margin:0 auto 2.4rem">A calm studio for fast, durable websites made of ordinary files you can host, move, and own — forever.</p>
  <div style="display:flex;gap:1rem;flex-wrap:wrap;justify-content:center">
    <a href="#" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.88rem;padding:.9em 1.6em;background:var(--color-text);color:var(--color-bg);border:1px solid var(--color-text);text-decoration:none">Get started →</a>
    <a href="#" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.88rem;padding:.9em 1.6em;background:transparent;color:var(--color-text);border:1px solid var(--color-border);text-decoration:none">See examples</a>
  </div>
</div>
HTML;
        return [
            'slug' => 'starter-hero-centered',
            'name' => 'Hero — Centered',
            'category' => 'Hero',
            'tags' => ['hero'],
            'description' => 'A centered headline, sub-headline, and two calls to action.',
            'blocks' => [$this->section([
                $this->row('1', [$this->column([$this->block('html-embed', ['html' => $html])])]),
            ], ['padding_top' => '0', 'padding_bottom' => '0', 'max_width' => '100%'])],
        ];
    }

    private function heroSplit(): array
    {
        $left = <<<'HTML'
<div style="padding:clamp(1rem,4vh,2.5rem) 0">
  <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.22em;font-weight:600;font-size:.76rem;color:var(--color-text-muted);margin:0 0 1.4rem">Design → Publish</p>
  <h1 style="font-family:var(--font-heading);font-weight:600;font-size:clamp(2.2rem,4.4vw,3.6rem);line-height:1.05;letter-spacing:-.02em;color:var(--color-heading);margin:0 0 1.2rem">Your website should belong to you.</h1>
  <p style="font-size:1.1rem;line-height:1.6;color:var(--color-text-muted);max-width:42ch;margin:0 0 2rem">Build visually, publish static files, and keep everything portable. No plugins, no lock-in.</p>
  <a href="#" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.88rem;padding:.9em 1.6em;background:var(--color-primary);color:var(--color-bg);border:1px solid var(--color-primary);text-decoration:none">Start building →</a>
</div>
HTML;
        $right = <<<'HTML'
<div style="aspect-ratio:4/3;border:1px solid var(--color-border);background:var(--color-bg-alt);display:flex;align-items:center;justify-content:center">
  <span style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.7rem;color:var(--color-text-muted)">Replace with an image</span>
</div>
HTML;
        return [
            'slug' => 'starter-hero-split',
            'name' => 'Hero — Split',
            'category' => 'Hero',
            'tags' => ['hero'],
            'description' => 'Headline and CTA beside a large image placeholder.',
            'blocks' => [$this->section([
                $this->row('1/2+1/2', [
                    $this->column([$this->block('html-embed', ['html' => $left])]),
                    $this->column([$this->block('html-embed', ['html' => $right])]),
                ]),
            ], ['padding_top' => '64px', 'padding_bottom' => '64px'])],
        ];
    }

    private function featuresTrio(): array
    {
        // Native, fully editable blocks — an example of the block-built pattern.
        $feature = fn (string $h, string $p) => $this->column([
            $this->block('heading', ['text' => $h, 'level' => 'h3', 'fontSize' => '1.35rem']),
            $this->block('paragraph', ['content' => "<p>{$p}</p>"], ['typography' => ['color' => 'var(--color-text-muted)']]),
        ]);

        return [
            'slug' => 'starter-features-trio',
            'name' => 'Features — Three columns',
            'category' => 'Features',
            'tags' => ['features', 'grid'],
            'description' => 'An intro plus three editable feature columns.',
            'blocks' => [$this->section([
                $this->row('1', [$this->column([
                    $this->block('paragraph', ['content' => '<p>What you get</p>'], ['typography' => ['color' => 'var(--color-text-muted)', 'letterSpacing' => '0.2em', 'textTransform' => 'uppercase', 'fontSize' => '0.78rem']]),
                    $this->block('heading', ['text' => 'Everything you need to ship.', 'level' => 'h2']),
                ])]),
                $this->row('1/3+1/3+1/3', [
                    $feature('Edit visually', 'Build pages with structured blocks and live previews. Your content is data, not markup.'),
                    $feature('Publish instantly', 'Generate fast static pages ready for any host or CDN. No server application required.'),
                    $feature('Keep everything', 'Content, media, and design tokens stay portable. Export anytime, host anywhere.'),
                ]),
            ], ['padding_top' => '80px', 'padding_bottom' => '80px'])],
        ];
    }

    private function callToAction(): array
    {
        $html = <<<'HTML'
<div style="text-align:center;padding:clamp(2.5rem,6vh,4.5rem) 1.25rem">
  <h2 style="font-family:var(--font-heading);font-weight:600;font-size:clamp(1.9rem,3.6vw,3rem);line-height:1.1;letter-spacing:-.01em;color:var(--color-heading);margin:0 0 1rem">Ready to build your next page?</h2>
  <p style="font-size:1.1rem;line-height:1.6;color:var(--color-text-muted);max-width:44ch;margin:0 auto 2rem">Start from a starter section, change the words, and publish in minutes.</p>
  <a href="#" style="display:inline-flex;align-items:center;gap:.5em;font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.88rem;padding:.95em 1.8em;background:var(--color-text);color:var(--color-bg);border:1px solid var(--color-text);text-decoration:none">Get started →</a>
</div>
HTML;
        return [
            'slug' => 'starter-cta',
            'name' => 'Call to action',
            'category' => 'Call to action',
            'tags' => ['cta'],
            'description' => 'A centered call-to-action band on an alternate background.',
            'blocks' => [$this->section(
                [$this->row('1', [$this->column([$this->block('html-embed', ['html' => $html])])])],
                ['padding_top' => '24px', 'padding_bottom' => '24px', 'max_width' => '100%'],
                ['visual' => ['backgroundColor' => 'var(--color-bg-alt)']],
            )],
        ];
    }

    private function statsBand(): array
    {
        $stat = fn (string $n, string $l) => <<<HTML
<div style="text-align:center;padding:1rem">
  <div style="font-family:var(--font-heading);font-weight:600;font-size:clamp(2.4rem,4vw,3.4rem);line-height:1;color:var(--color-heading)">{$n}</div>
  <div style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.12em;font-size:.72rem;color:var(--color-text-muted);margin-top:.6rem">{$l}</div>
</div>
HTML;
        $html = '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem">'
            . $stat('99%', 'Uptime')
            . $stat('12k', 'Sites built')
            . $stat('&lt;1s', 'Load time')
            . $stat('0', 'Plugins needed')
            . '<style>@media(max-width:640px){div[style*="repeat(4"]{grid-template-columns:repeat(2,1fr) !important}}</style></div>';

        return [
            'slug' => 'starter-stats',
            'name' => 'Stats — Four metrics',
            'category' => 'Social proof',
            'tags' => ['stats', 'metrics'],
            'description' => 'A row of four headline numbers with labels.',
            'blocks' => [$this->section([
                $this->row('1', [$this->column([$this->block('html-embed', ['html' => $html])])]),
            ], ['padding_top' => '56px', 'padding_bottom' => '56px'])],
        ];
    }

    private function testimonial(): array
    {
        $html = <<<'HTML'
<figure style="max-width:720px;margin:0 auto;text-align:center;padding:1.25rem">
  <blockquote style="font-family:var(--font-heading);font-weight:500;font-size:clamp(1.4rem,2.6vw,2rem);line-height:1.35;letter-spacing:-.01em;color:var(--color-heading);margin:0 0 1.6rem">“The calmest editor I've used. We shipped our site in an afternoon and never looked back.”</blockquote>
  <figcaption style="font-family:var(--font-heading);font-size:.82rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-text-muted)">Alex Rivera · Studio Forma</figcaption>
</figure>
HTML;
        return [
            'slug' => 'starter-testimonial',
            'name' => 'Testimonial — Centered quote',
            'category' => 'Social proof',
            'tags' => ['testimonial', 'quote'],
            'description' => 'A single centered pull-quote with attribution.',
            'blocks' => [$this->section([
                $this->row('1', [$this->column([$this->block('html-embed', ['html' => $html])])]),
            ], ['padding_top' => '72px', 'padding_bottom' => '72px'])],
        ];
    }

    private function contentImage(): array
    {
        $text = <<<'HTML'
<div style="padding:clamp(.5rem,3vh,2rem) 0">
  <p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.2em;font-weight:600;font-size:.76rem;color:var(--color-text-muted);margin:0 0 1rem">How it works</p>
  <h2 style="font-family:var(--font-heading);font-weight:600;font-size:clamp(1.8rem,3.2vw,2.6rem);line-height:1.1;color:var(--color-heading);margin:0 0 1rem">A studio for making pages, not managing software.</h2>
  <p style="font-size:1.05rem;line-height:1.65;color:var(--color-text-muted);max-width:46ch;margin:0 0 1.5rem">Write, arrange, and refine in one focused space. Everything you publish is a clean, portable file.</p>
  <a href="#" style="font-family:var(--font-heading);font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:.84rem;color:var(--color-primary);text-decoration:none">Learn more →</a>
</div>
HTML;
        $img = <<<'HTML'
<div style="aspect-ratio:1/1;border:1px solid var(--color-border);background:var(--color-bg-alt);display:flex;align-items:center;justify-content:center">
  <span style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.14em;font-size:.7rem;color:var(--color-text-muted)">Image</span>
</div>
HTML;
        return [
            'slug' => 'starter-content-image',
            'name' => 'Content + Image',
            'category' => 'Content',
            'tags' => ['content', 'split'],
            'description' => 'A text column beside a square image placeholder.',
            'blocks' => [$this->section([
                $this->row('2/3+1/3', [
                    $this->column([$this->block('html-embed', ['html' => $text])]),
                    $this->column([$this->block('html-embed', ['html' => $img])]),
                ]),
            ], ['padding_top' => '72px', 'padding_bottom' => '72px'])],
        ];
    }

    private function logoStrip(): array
    {
        $logo = fn (string $n) => '<div style="display:flex;align-items:center;justify-content:center;height:44px;border:1px solid var(--color-border);font-family:var(--font-heading);font-weight:600;letter-spacing:.06em;color:var(--color-text-muted);font-size:.9rem">' . $n . '</div>';
        $html = '<div style="text-align:center;padding:.5rem 1.25rem">'
            . '<p style="font-family:var(--font-heading);text-transform:uppercase;letter-spacing:.18em;font-size:.72rem;color:var(--color-text-muted);margin:0 0 1.6rem">Trusted by teams everywhere</p>'
            . '<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem">'
            . $logo('NORTH') . $logo('Atlas') . $logo('FORMA') . $logo('Ledger') . $logo('HEARTH')
            . '</div><style>@media(max-width:640px){div[style*="repeat(5"]{grid-template-columns:repeat(2,1fr) !important}}</style></div>';

        return [
            'slug' => 'starter-logo-strip',
            'name' => 'Logo strip',
            'category' => 'Social proof',
            'tags' => ['logos', 'social-proof'],
            'description' => 'An eyebrow line above a row of placeholder logos.',
            'blocks' => [$this->section([
                $this->row('1', [$this->column([$this->block('html-embed', ['html' => $html])])]),
            ], ['padding_top' => '48px', 'padding_bottom' => '48px'])],
        ];
    }

    /* ─────────────────────────────── helpers ─────────────────────────────── */

    private function section(array $children, array $data = [], ?array $style = null): array
    {
        $this->rowOrder = $this->colOrder = $this->blockOrder = 0;
        $node = [
            'id' => Str::uuid()->toString(),
            'type' => 'section',
            'level' => 'section',
            'order' => 0,
            'data' => array_merge(['padding_top' => '40px', 'padding_bottom' => '40px', 'max_width' => '1200px'], $data),
            'children' => $children,
        ];
        if ($style) $node['style'] = $style;
        return $node;
    }

    private function row(string $layout, array $children): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'row',
            'level' => 'row',
            'order' => $this->rowOrder++,
            'data' => ['layout' => $layout, 'gap' => '32px'],
            'children' => $children,
        ];
    }

    private function column(array $children): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'type' => 'column',
            'level' => 'column',
            'order' => $this->colOrder++,
            'data' => [],
            'children' => $children,
        ];
    }

    private function block(string $type, array $data, ?array $style = null): array
    {
        $node = [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'level' => 'module',
            'order' => $this->blockOrder++,
            'data' => $data,
            'children' => [],
        ];
        if ($style) $node['style'] = $style;
        return $node;
    }
}
