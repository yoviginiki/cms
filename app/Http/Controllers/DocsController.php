<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocsController extends Controller
{
    private string $docsPath;

    public function __construct()
    {
        $this->docsPath = base_path('docs');
    }

    public function index()
    {
        $docs = $this->listDocs();
        if (empty($docs)) {
            return view('docs.layout', [
                'title' => 'Documentation',
                'docs' => [],
                'current' => '',
                'content' => '<h1>Documentation</h1><p>No documentation files found. Add <code>.md</code> files to the <code>docs/</code> directory.</p>',
            ]);
        }

        // Render index page with doc listing instead of redirect
        $links = implode('', array_map(fn($d) => "<li><a href=\"/docs/{$d['slug']}\">{$d['title']}</a></li>", $docs));
        return view('docs.layout', [
            'title' => 'Documentation',
            'docs' => $docs,
            'current' => '',
            'content' => "<h1>Documentation</h1><p>New here? Start with <a href=\"/docs/MANUAL\">Manual: Build Your First Site</a>. Use the search box on the left (press <code>/</code>) to find anything.</p><ul>{$links}</ul>",
        ]);
    }

    /**
     * Full-text search across every docs/*.md file. All query terms must
     * appear in a doc (case-insensitive, Cyrillic-safe); title/heading hits
     * rank above body hits. Each result links to the nearest heading anchor.
     */
    public function search(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $docs = $this->listDocs();
        $results = $query === '' ? [] : $this->searchDocs($query);

        return view('docs.layout', [
            'title' => $query === '' ? 'Search' : "Search: {$query}",
            'docs' => $docs,
            'current' => '',
            'query' => $query,
            'content' => view('docs.search', ['query' => $query, 'results' => $results])->render(),
        ]);
    }

    public function download()
    {
        $zipName = 'cms-docs-' . now()->format('Y-m-d') . '.zip';
        $zipPath = storage_path("app/tmp/{$zipName}");
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Failed to create ZIP');
        }

        // Include README.md from project root
        $readme = base_path('README.md');
        if (File::exists($readme)) {
            $zip->addFile($readme, 'README.md');
        }

        foreach (File::glob("{$this->docsPath}/*.md") as $file) {
            $zip->addFile($file, 'docs/' . basename($file));
        }

        $zip->close();

        return response()->download($zipPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function show(string $slug)
    {
        // Old links point at "/docs/foo.md" — redirect to the canonical slug.
        if (str_ends_with($slug, '.md')) {
            return redirect('/docs/' . substr($slug, 0, -3), 301);
        }

        $file = $this->findFile($slug);
        if (!$file) {
            abort(404);
        }

        $docs = $this->listDocs();
        $filePath = "{$this->docsPath}/{$file}";

        $markdown = File::get($filePath);
        $html = $this->renderMarkdown($markdown);
        $title = $this->extractTitle($markdown) ?: Str::headline($slug);

        return view('docs.layout', [
            'title' => $title,
            'docs' => $docs,
            'current' => $slug,
            'query' => trim((string) request()->query('hl', '')),
            'content' => $html,
        ]);
    }

    private function listDocs(): array
    {
        if (!File::isDirectory($this->docsPath)) {
            return [];
        }

        $files = File::glob("{$this->docsPath}/*.md");
        sort($files);

        return array_map(function ($file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $content = File::get($file);
            $title = $this->extractTitle($content) ?: Str::headline($filename);

            return [
                'slug' => $filename,
                'title' => $title,
                'file' => basename($file),
            ];
        }, $files);
    }

    /**
     * @return array<int, array{slug: string, title: string, score: int, hits: array<int, array{heading: ?string, anchor: ?string, html: string}>}>
     */
    public function searchDocs(string $query, int $limit = 30): array
    {
        $terms = array_values(array_unique(array_filter(
            preg_split('/\s+/u', mb_strtolower($query)) ?: [],
            fn ($t) => mb_strlen($t) >= 2
        )));
        if (empty($terms)) {
            return [];
        }

        $results = [];
        foreach (File::glob("{$this->docsPath}/*.md") as $file) {
            $markdown = File::get($file);
            $lower = mb_strtolower($markdown);

            foreach ($terms as $term) {
                if (mb_strpos($lower, $term) === false) {
                    continue 2;
                }
            }

            $slug = pathinfo($file, PATHINFO_FILENAME);
            $title = $this->extractTitle($markdown) ?: Str::headline($slug);
            $titleLower = mb_strtolower($title);

            $score = 0;
            foreach ($terms as $term) {
                if (mb_strpos($titleLower, $term) !== false) $score += 50;
                $score += min(20, mb_substr_count($lower, $term));
            }

            $hits = [];
            $heading = null;
            $anchor = null;
            $anchors = [];
            $inFence = false;
            foreach (preg_split('/\R/u', $markdown) as $line) {
                if (str_starts_with(ltrim($line), '```')) {
                    $inFence = !$inFence;
                }
                if (!$inFence && preg_match('/^(#{1,6})\s+(.+)$/u', $line, $m)) {
                    $heading = trim($m[2]);
                    $anchor = $this->uniqueAnchor($heading, $anchors);
                    $headingLower = mb_strtolower($heading);
                    foreach ($terms as $term) {
                        if (mb_strpos($headingLower, $term) !== false) $score += 10;
                    }
                }

                if (count($hits) >= 3 || trim($line) === '') {
                    continue;
                }
                $lineLower = mb_strtolower($line);
                foreach ($terms as $term) {
                    if (mb_strpos($lineLower, $term) !== false) {
                        $hits[] = [
                            'heading' => $heading,
                            'anchor' => $anchor,
                            'html' => $this->highlight($this->snippet($line, $term), $terms),
                        ];
                        break;
                    }
                }
            }

            $results[] = ['slug' => $slug, 'title' => $title, 'score' => $score, 'hits' => $hits];
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score'] ?: strcmp($a['title'], $b['title']));

        return array_slice($results, 0, $limit);
    }

    /** ~200 chars of plain text around the first occurrence of $term. */
    private function snippet(string $line, string $term): string
    {
        $text = trim(preg_replace('/[*`#>|]+/u', ' ', $line));
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        $pos = mb_strpos(mb_strtolower($text), $term) ?: 0;
        $start = max(0, $pos - 80);
        $out = mb_substr($text, $start, 200);

        return ($start > 0 ? '…' : '') . $out . ($start + 200 < mb_strlen($text) ? '…' : '');
    }

    private function highlight(string $text, array $terms): string
    {
        $pattern = '/(' . implode('|', array_map(fn ($t) => preg_quote($t, '/'), $terms)) . ')/iu';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $html = '';
        foreach ($parts as $i => $part) {
            $html .= $i % 2 ? '<mark>' . e($part) . '</mark>' : e($part);
        }
        return $html;
    }

    /** Heading → id, shared by the renderer and search so result links land on the heading. */
    private function uniqueAnchor(string $heading, array &$used): string
    {
        $base = Str::slug(strip_tags(html_entity_decode($heading))) ?: 'section';
        $id = $base;
        $n = 2;
        while (isset($used[$id])) {
            $id = $base . '-' . $n++;
        }
        $used[$id] = true;
        return $id;
    }

    private function findFile(string $slug): ?string
    {
        $file = "{$slug}.md";
        if (preg_match('/^[A-Za-z0-9._-]+$/', $slug) && File::isFile("{$this->docsPath}/{$file}")) {
            return $file;
        }
        return null;
    }

    private function extractTitle(string $markdown): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function renderMarkdown(string $markdown): string
    {
        // Simple markdown to HTML conversion without external dependencies
        $html = e($markdown);

        // Code blocks (fenced) — stashed so the line rules below (headings,
        // lists, paragraphs) never touch code; restored at the end.
        $stash = [];
        $html = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) use (&$stash) {
            $lang = $m[1] ? " class=\"language-{$m[1]}\"" : '';
            $stash[] = "<pre><code{$lang}>{$m[2]}</code></pre>";
            return '<pre data-stash="' . (count($stash) - 1) . '"></pre>';
        }, $html);

        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Headers (with ids — same anchors searchDocs() links to). Anchors are
        // computed from the raw markdown heading text so both sides agree.
        $anchors = [];
        $html = preg_replace_callback('/^(#{1,6})\s+(.+)$/m', function ($m) use (&$anchors) {
            $level = strlen($m[1]);
            $id = $this->uniqueAnchor(html_entity_decode(strip_tags($m[2])), $anchors);
            return "<h{$level} id=\"{$id}\">{$m[2]}</h{$level}>";
        }, $html);

        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Links
        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html);

        // Blockquotes
        $html = preg_replace('/^&gt;\s+(.+)$/m', '<blockquote>$1</blockquote>', $html);

        // Tables
        $html = preg_replace_callback('/^(\|.+\|)\n(\|[-| :]+\|)\n((?:\|.+\|\n?)+)/m', function ($m) {
            $headerRow = trim($m[1]);
            $bodyRows = trim($m[3]);

            $headers = array_map('trim', explode('|', trim($headerRow, '|')));
            $thead = '<thead><tr>' . implode('', array_map(fn($h) => "<th>{$h}</th>", $headers)) . '</tr></thead>';

            $tbody = '<tbody>';
            foreach (explode("\n", $bodyRows) as $row) {
                $cells = array_map('trim', explode('|', trim($row, '|')));
                $tbody .= '<tr>' . implode('', array_map(fn($c) => "<td>{$c}</td>", $cells)) . '</tr>';
            }
            $tbody .= '</tbody>';

            return "<table>{$thead}{$tbody}</table>";
        }, $html);

        // Unordered lists
        $html = preg_replace_callback('/^(?:- .+\n?)+/m', function ($m) {
            $items = preg_replace('/^- (.+)$/m', '<li>$1</li>', $m[0]);
            return "<ul>{$items}</ul>";
        }, $html);

        // Ordered lists
        $html = preg_replace_callback('/^(?:\d+\. .+\n?)+/m', function ($m) {
            $items = preg_replace('/^\d+\. (.+)$/m', '<li>$1</li>', $m[0]);
            return "<ol>{$items}</ol>";
        }, $html);

        // Horizontal rules
        $html = preg_replace('/^---+$/m', '<hr>', $html);

        // Paragraphs — wrap remaining text lines
        $html = preg_replace('/^(?!<[a-z]|$)(.+)$/m', '<p>$1</p>', $html);

        // Clean up empty paragraphs
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);

        $html = preg_replace_callback('/<pre data-stash="(\d+)"><\/pre>/', fn ($m) => $stash[(int) $m[1]], $html);

        return $html;
    }
}
