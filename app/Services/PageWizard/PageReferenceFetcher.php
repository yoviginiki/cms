<?php

namespace App\Services\PageWizard;

use App\Support\SsrfGuard;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Content mode: fetch a URL's HTML (SSRF-gated) and reduce it to a compact,
 * readable outline — title, headings, paragraphs, and image srcs — that the
 * content model lays into a page. We never execute the page or follow its
 * assets; just parse the returned HTML text.
 */
class PageReferenceFetcher
{
    private const MAX_BYTES = 3_000_000;
    private const MAX_OUTLINE_CHARS = 12000;

    /** @return array{title: string, outline: string, images: array<int,string>} */
    public function fetch(string $url): array
    {
        SsrfGuard::assertPublicHttpUrl($url);

        try {
            $response = Http::withHeaders(['User-Agent' => 'StillopressPageWizard/1.0'])
                ->timeout(20)
                ->withOptions(['allow_redirects' => ['max' => 3, 'strict' => true]])
                ->get($url);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not load that page — check the URL or try a screenshot instead.');
        }

        if (!$response->successful()) {
            throw new RuntimeException("That page returned HTTP {$response->status()}.");
        }

        $html = mb_substr((string) $response->body(), 0, self::MAX_BYTES);
        if (trim($html) === '') {
            throw new RuntimeException('That page had no readable content.');
        }

        return [
            'title' => $this->title($html, $url),
            'outline' => $this->outline($html),
            'images' => $this->images($html, $url),
        ];
    }

    private function title(string $html, string $url): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
            if ($t !== '') {
                return mb_substr($t, 0, 120);
            }
        }

        return mb_substr((string) parse_url($url, PHP_URL_HOST), 0, 120) ?: 'Imported page';
    }

    /** Ordered headings + paragraphs as plain text, tagged so the AI sees structure. */
    private function outline(string $html): string
    {
        // Drop script/style/noscript wholesale.
        $body = preg_replace('/<(script|style|noscript|template|svg)[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;

        preg_match_all('/<(h[1-6]|p|li)[^>]*>(.*?)<\/\1>/is', $body, $matches, PREG_SET_ORDER);

        $lines = [];
        foreach ($matches as $m) {
            $tag = mb_strtolower($m[1]);
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if ($text === '' || mb_strlen($text) < 2) {
                continue;
            }
            $label = str_starts_with($tag, 'h') ? strtoupper($tag) : ($tag === 'li' ? '- ' : 'P');
            $lines[] = str_starts_with($tag, 'h') ? "[{$label}] {$text}" : ($tag === 'li' ? "- {$text}" : $text);
            if (mb_strlen(implode("\n", $lines)) > self::MAX_OUTLINE_CHARS) {
                break;
            }
        }

        $outline = trim(implode("\n", $lines));
        if ($outline === '') {
            throw new RuntimeException('That page had no readable headings or text.');
        }

        return mb_substr($outline, 0, self::MAX_OUTLINE_CHARS);
    }

    /** @return array<int, string> absolute http(s) image URLs (deduped, capped) */
    private function images(string $html, string $base): array
    {
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m);
        $out = [];
        foreach ($m[1] as $src) {
            $abs = $this->absolutize(trim($src), $base);
            if ($abs && preg_match('/^https?:\/\//i', $abs) && !in_array($abs, $out, true)) {
                $out[] = $abs;
            }
            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }

    private function absolutize(string $src, string $base): ?string
    {
        if ($src === '' || str_starts_with($src, 'data:')) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $src)) {
            return $src;
        }
        $b = parse_url($base);
        if (empty($b['scheme']) || empty($b['host'])) {
            return null;
        }
        $origin = "{$b['scheme']}://{$b['host']}" . (isset($b['port']) ? ":{$b['port']}" : '');
        if (str_starts_with($src, '//')) {
            return "{$b['scheme']}:{$src}";
        }
        if (str_starts_with($src, '/')) {
            return $origin . $src;
        }

        return $origin . '/' . ltrim($src, '/');
    }
}
