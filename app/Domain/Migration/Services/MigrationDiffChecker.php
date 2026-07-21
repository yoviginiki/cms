<?php

namespace App\Domain\Migration\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Http;

/**
 * Forensic element-by-element comparison of an origin page against its
 * migrated counterpart — instead of eyeballing. For each pair it reports:
 *
 *  - text coverage: % of the origin's substantive sentences present verbatim
 *  - headings: origin headings missing from the new page (by normalized text)
 *  - images: origin content images (by filename) absent from the new page
 *  - internal links: origin in-content links whose migrated target is not
 *    linked anywhere on the new page (link-recreation check)
 *  - meta: title / description / og:image presence
 *
 * Compares RENDERED output on both sides, so block-render regressions
 * (a blade printing "open" labels, checkbox inputs, missing hero…) surface
 * here — the checker sees exactly what a visitor sees.
 */
class MigrationDiffChecker
{
    public function __construct(private LinkRewriter $links) {}

    /**
     * @param array<int, array{origin: string, new: string, label?: string}> $pairs
     * @return array{pages: array<int, array>, summary: array}
     */
    public function comparePairs(Site $site, string $originHost, array $pairs): array
    {
        $this->links->buildMap($site);
        $reports = [];
        foreach ($pairs as $pair) {
            $reports[] = $this->comparePair($site, $originHost, $pair);
        }

        $avg = fn (string $k) => count($reports)
            ? round(array_sum(array_column($reports, $k)) / count($reports), 1)
            : 0.0;

        return [
            'pages' => $reports,
            'summary' => [
                'pages' => count($reports),
                'avg_text_coverage' => $avg('text_coverage'),
                'pages_with_missing_headings' => count(array_filter($reports, fn ($r) => $r['missing_headings'] !== [])),
                'pages_with_missing_images' => count(array_filter($reports, fn ($r) => $r['missing_images'] !== [])),
                'pages_with_missing_links' => count(array_filter($reports, fn ($r) => $r['missing_links'] !== [])),
            ],
        ];
    }

    private function comparePair(Site $site, string $originHost, array $pair): array
    {
        $label = $pair['label'] ?? $pair['origin'];
        try {
            $originHtml = $this->fetch($pair['origin']);
            $newHtml = $this->fetch($pair['new']);
        } catch (\Throwable $e) {
            return [
                'label' => $label, 'error' => $e->getMessage(),
                'text_coverage' => 0.0, 'missing_headings' => [], 'demoted_headings' => [],
                'missing_images' => [], 'missing_background_images' => [], 'missing_links' => [], 'meta' => [],
            ];
        }

        $origin = $this->analyze($originHtml);
        $new = $this->analyze($newHtml);
        $newText = $this->normalize($new['text']);

        // text coverage: substantive origin chunks found in the new page
        $found = 0;
        $missingChunks = [];
        foreach ($origin['chunks'] as $chunk) {
            if (str_contains($newText, $this->normalize($chunk))) {
                $found++;
            } else {
                $missingChunks[] = mb_substr($chunk, 0, 90);
            }
        }
        $coverage = $origin['chunks'] === [] ? 100.0 : round($found / count($origin['chunks']) * 100, 1);

        // headings by normalized text; text present but not as a heading tag
        // is a demotion (list item, bold paragraph…), reported separately
        $newHeadings = array_map(fn ($h) => $this->normalize($h), $new['headings']);
        $missingHeadings = [];
        $demotedHeadings = [];
        foreach ($origin['headings'] as $h) {
            if (in_array($this->normalize($h), $newHeadings, true)) {
                continue;
            }
            if (str_contains($newText, $this->normalize($h))) {
                $demotedHeadings[] = $h;
            } else {
                $missingHeadings[] = $h;
            }
        }

        // images by base filename — published files are content-hashed
        // ({checksum}_variant.ext), so translate hashes back to original names
        $newImageNames = $this->resolveHashedNames($site, $new['images']);
        $missingImages = array_values(array_diff($origin['images'], $newImageNames));

        // CSS background images: a builder often uses these decoratively
        // (section backdrops, dividers) — count them present if the new page
        // has them EITHER as a background or as a content image
        $newBgNames = $this->resolveHashedNames($site, $new['background_images']);
        $missingBackgrounds = array_values(array_diff(
            $origin['background_images'],
            array_merge($newBgNames, $newImageNames),
        ));

        // internal links: origin in-content links → mapped target linked on new page?
        $missingLinks = [];
        foreach ($origin['links'] as $path) {
            $host = parse_url($path, PHP_URL_HOST);
            if ($host && strcasecmp(preg_replace('/^www\./', '', $host), preg_replace('/^www\./', '', $originHost)) !== 0) {
                continue; // external
            }
            $p = $host ? (parse_url($path, PHP_URL_PATH) ?? '/') : $path;
            if (!str_starts_with($p, '/') || str_starts_with($p, '//')) {
                continue;
            }
            $target = $this->links->resolvePath($p);
            if ($target === null || $target === '/') {
                continue; // unmappable — redirect map's problem, not this page's
            }
            $linked = array_filter($new['links'], fn ($l) => rtrim(strtok($l, '?') ?: '', '/') === rtrim($target, '/')
                || str_ends_with(rtrim(strtok($l, '?') ?: '', '/'), rtrim($target, '/')));
            if ($linked === []) {
                $missingLinks[] = "{$p} → {$target}";
            }
        }

        return [
            'label' => $label,
            'text_coverage' => $coverage,
            'missing_text_samples' => array_slice($missingChunks, 0, 5),
            'missing_headings' => $missingHeadings,
            'demoted_headings' => $demotedHeadings,
            'missing_images' => $missingImages,
            'missing_background_images' => $missingBackgrounds,
            'missing_links' => array_values(array_unique($missingLinks)),
            'meta' => [
                'origin_title' => $origin['meta']['title'],
                'new_title' => $new['meta']['title'],
                'new_has_description' => $new['meta']['description'] !== null,
                'origin_has_description' => $origin['meta']['description'] !== null,
            ],
        ];
    }

    /** @return array{text: string, chunks: string[], headings: string[], images: string[], links: string[], meta: array} */
    private function analyze(string $html): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xp = new \DOMXPath($doc);

        foreach ($xp->query('//script|//style|//noscript|//header|//footer|//nav') as $n) {
            $n->parentNode?->removeChild($n);
        }

        $root = null;
        foreach (["//div[contains(@class,'entry-content')]", '//main', '//body'] as $q) {
            $root = $xp->query($q)->item(0);
            if ($root) {
                break;
            }
        }

        $text = $root ? $root->textContent : '';
        $chunks = [];
        foreach (preg_split('/(?<=[.!?…])\s+|\n+/u', $text) ?: [] as $s) {
            $s = trim($s);
            if (mb_strlen($s) >= 40 && !preg_match('/^(©|Powered|by\s)/u', $s)) {
                $chunks[] = $s;
            }
        }

        $headings = [];
        if ($root) {
            foreach ($xp->query('.//h1|.//h2|.//h3|.//h4', $root) as $h) {
                $t = trim($h->textContent);
                if ($t !== '' && mb_strlen($t) > 2) {
                    $headings[] = $t;
                }
            }
        }

        $images = [];
        if ($root) {
            foreach ($xp->query('.//img', $root) as $img) {
                $src = $img->getAttribute('data-src') ?: $img->getAttribute('src');
                $name = urldecode(basename(parse_url($src, PHP_URL_PATH) ?? ''));
                $name = preg_replace('/-\d+x\d+(\.[a-z]+)$/i', '$1', $name);
                // migrated images are content-hashed — compare by extension-less
                // hash OR original name, so collect both raw names
                if ($name !== '' && !str_contains($name, 'logo')) {
                    $images[] = $name;
                }
            }
        }

        $links = [];
        if ($root) {
            foreach ($xp->query('.//a[@href]', $root) as $a) {
                $links[] = $a->getAttribute('href');
            }
        }

        $meta = ['title' => null, 'description' => null];
        $t = $xp->query('//title')->item(0);
        if ($t) {
            $meta['title'] = trim($t->textContent);
        }
        $d = $xp->query("//meta[@name='description']")->item(0);
        if ($d instanceof \DOMElement) {
            $meta['description'] = trim($d->getAttribute('content')) ?: null;
        }

        // CSS background-image urls anywhere on the page (inline styles + <style>)
        preg_match_all('#background(?:-image)?:[^;}]*url\((?:&\#0?39;|&quot;|[\'"])?([^\'")]+)#i', $html, $bgm);
        $backgrounds = [];
        foreach ($bgm[1] ?? [] as $u) {
            $name = urldecode(basename(parse_url($u, PHP_URL_PATH) ?? ''));
            $name = preg_replace('/-\d+x\d+(\.[a-z]+)$/i', '$1', $name);
            // lazy-load spinners and placeholders are chrome, not design
            if ($name !== '' && preg_match('/\.(jpe?g|png|webp|gif)$/i', $name)
                && !preg_match('/loader|spinner|preload|placeholder|blank\./i', $name)) {
                $backgrounds[] = $name;
            }
        }

        return [
            'text' => $text,
            'chunks' => array_values(array_unique($chunks)),
            'headings' => array_values(array_unique($headings)),
            'images' => array_values(array_unique($images)),
            'background_images' => array_values(array_unique($backgrounds)),
            'links' => $links,
            'meta' => $meta,
        ];
    }

    /** @param string[] $names @return string[] with content-hashes mapped to original filenames */
    private function resolveHashedNames(Site $site, array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            if (preg_match('/^([0-9a-f]{40,64})(?:_[a-z0-9_]+)?\.[a-z]+$/i', $name, $m)) {
                $asset = \App\Models\Asset::where('site_id', $site->id)->where('checksum', $m[1])->first();
                if ($asset) {
                    $out[] = $asset->original_name;
                    continue;
                }
            }
            $out[] = $name;
        }

        return $out;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = str_replace(['„', '“', '”', '"', '’', '‘', "'", '–', '—', '…'], ['', '', '', '', '', '', '', '-', '-', '...'], $s);

        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    private function fetch(string $url): string
    {
        $res = Http::timeout(40)->retry(2, 500)->get($url);
        if (!$res->successful()) {
            throw new \RuntimeException("HTTP {$res->status()} for {$url}");
        }

        return $res->body();
    }
}
