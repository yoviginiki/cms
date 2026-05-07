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

        return redirect("/docs/{$docs[0]['slug']}");
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

        foreach (File::glob("{$this->docsPath}/*.md") as $file) {
            $zip->addFile($file, basename($file));
        }

        $zip->close();

        return response()->download($zipPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function show(string $slug)
    {
        $docs = $this->listDocs();
        $filePath = "{$this->docsPath}/{$this->findFile($slug)}";

        if (!$filePath || !File::exists($filePath)) {
            abort(404);
        }

        $markdown = File::get($filePath);
        $html = $this->renderMarkdown($markdown);
        $title = $this->extractTitle($markdown) ?: Str::headline($slug);

        return view('docs.layout', [
            'title' => $title,
            'docs' => $docs,
            'current' => $slug,
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

    private function findFile(string $slug): ?string
    {
        $file = "{$slug}.md";
        if (File::exists("{$this->docsPath}/{$file}")) {
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

        // Code blocks (fenced)
        $html = preg_replace_callback('/```(\w*)\n(.*?)```/s', function ($m) {
            $lang = $m[1] ? " class=\"language-{$m[1]}\"" : '';
            $code = $m[2];
            return "<pre><code{$lang}>{$code}</code></pre>";
        }, $html);

        // Inline code
        $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

        // Headers
        $html = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $html);
        $html = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $html);
        $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $html);

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

        return $html;
    }
}
