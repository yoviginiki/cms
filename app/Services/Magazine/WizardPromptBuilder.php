<?php

namespace App\Services\Magazine;

use App\Models\Magazine\WizardSession;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

class WizardPromptBuilder
{
    private string $promptDir;

    public function __construct()
    {
        $this->promptDir = storage_path('app/wizard');
    }

    /**
     * Build the full system prompt for a given step.
     */
    public function build(WizardSession $session, int $currentStep, ?array $currentArtifact = null): string
    {
        $parts = [];

        // 1. Base prompt
        $parts[] = $this->loadFile('base_prompt.txt');

        // 2. Current step instructions
        $parts[] = "────────────────────────────────────────";
        $parts[] = "CURRENT STEP: {$currentStep}";
        $parts[] = "────────────────────────────────────────";
        $parts[] = $this->loadFile("step_{$currentStep}.txt");

        // 3. Locked decisions from prior steps
        $locked = $this->buildLockedDecisions($session, $currentStep);
        if ($locked) {
            $parts[] = "\n<locked_decisions>\n{$locked}\n</locked_decisions>";
        }

        // 4. Article inventory for steps 2+ (so AI knows what content exists)
        if ($currentStep >= 2) {
            $inventory = $this->buildArticleInventory($session);
            if ($inventory) {
                $parts[] = "\n<article_inventory>\n{$inventory}\n</article_inventory>";
            }
        }

        // 5. Selected article full text for steps 4-6
        if ($currentStep >= 4 && $currentStep <= 6) {
            $articleText = $this->buildSelectedArticleText($session);
            if ($articleText) {
                $parts[] = "\n<selected_article_text>\n{$articleText}\n</selected_article_text>";
            }
        }

        // 6. Current artifact state
        if ($currentArtifact !== null) {
            $json = json_encode($currentArtifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $parts[] = "\n<current_artifact>\n{$json}\n</current_artifact>";
        }

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Get the conversation messages for the current step (last 10).
     * Ensures the sequence always ends with a user message (API requirement).
     * Also ensures the sequence starts with a user message and alternates correctly.
     */
    public function getStepMessages(WizardSession $session, int $step): array
    {
        $messages = $session->messages()
            ->where('step', $step)
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ])
            ->toArray();

        if (empty($messages)) return [];

        // Ensure starts with user message
        while (!empty($messages) && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        // Ensure ends with user message (API requires this)
        while (!empty($messages) && end($messages)['role'] !== 'user') {
            array_pop($messages);
        }

        // Merge consecutive same-role messages (shouldn't happen, but safety)
        $cleaned = [];
        $lastRole = null;
        foreach ($messages as $msg) {
            if ($msg['role'] === $lastRole) {
                $cleaned[count($cleaned) - 1]['content'] .= "\n\n" . $msg['content'];
            } else {
                $cleaned[] = $msg;
                $lastRole = $msg['role'];
            }
        }

        // Take last 10
        return array_slice($cleaned, -10);
    }

    /**
     * Build inventory of all posts in the tenant's site.
     * Includes title, word count, whether it has an image, and category.
     */
    private function buildArticleInventory(WizardSession $session): string
    {
        try {
            $site = Site::first();
            if (!$site) return '';

            $posts = $site->posts()
                ->with('category')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($posts->isEmpty()) return 'No articles found in the CMS.';

            $lines = ["Available articles from the CMS:\n"];
            foreach ($posts as $post) {
                if (!$post->title || in_array($post->title, ['', 'ttt', 'test', 'test2', 'Hello world', 'First Blog Post'])) {
                    continue;
                }

                // Get word count from blocks
                $blocks = $post->blocks()
                    ->whereIn('type', ['text', 'paragraph', 'rich_text'])
                    ->get();
                $body = '';
                foreach ($blocks as $b) {
                    $body .= strip_tags($b->data['content'] ?? '') . ' ';
                }
                $words = count(preg_split('/[\s]+/u', trim($body), -1, PREG_SPLIT_NO_EMPTY));

                $hasImage = $post->featured_image ? 'has image' : 'no image';
                $category = $post->category ? $post->category->name : 'uncategorized';

                $lines[] = "- \"{$post->title}\" | {$words} words | {$hasImage} | category: {$category} | slug: {$post->slug}";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return '<!-- Failed to load article inventory: ' . $e->getMessage() . ' -->';
        }
    }

    /**
     * Build the full text of the selected article (from step 3).
     * Used in steps 4-6 so the AI can analyze the actual content.
     */
    private function buildSelectedArticleText(WizardSession $session): string
    {
        try {
            $selection = $session->step3_article_selection;
            if (!$selection || empty($selection['selected_slug'])) {
                return '';
            }

            $slug = $selection['selected_slug'];

            // Find the post by slug
            $site = Site::first();
            if (!$site) return '';

            $post = $site->posts()->where('slug', $slug)->first();
            if (!$post) {
                // Try matching by title from the structure
                $structure = $session->step2_structure;
                $articleDef = collect($structure['articles'] ?? [])->firstWhere('slug', $slug);
                $title = $articleDef['title'] ?? $slug;

                $post = $site->posts()->where('title', $title)->first();
                if (!$post) return "Article \"{$slug}\" not found in CMS. It may be a custom text entry.";
            }

            // Extract full text from blocks
            $blocks = $post->blocks()
                ->whereIn('type', ['text', 'paragraph', 'rich_text', 'heading', 'quote'])
                ->orderBy('order')
                ->get();

            $parts = [];
            $parts[] = "Title: {$post->title}";
            if ($post->excerpt) $parts[] = "Excerpt: {$post->excerpt}";
            if ($post->featured_image) $parts[] = "Featured image: yes";
            $parts[] = "";

            foreach ($blocks as $block) {
                $content = strip_tags($block->data['content'] ?? '');
                if (trim($content)) {
                    if ($block->type === 'heading') {
                        $parts[] = "## " . trim($content);
                    } elseif ($block->type === 'quote') {
                        $parts[] = "> " . trim($content);
                    } else {
                        $parts[] = trim($content);
                    }
                    $parts[] = "";
                }
            }

            $fullText = implode("\n", $parts);

            // Truncate if extremely long (>8000 words)
            $words = preg_split('/[\s]+/u', $fullText, -1, PREG_SPLIT_NO_EMPTY);
            if (count($words) > 8000) {
                $fullText = implode(' ', array_slice($words, 0, 8000)) . "\n\n[... truncated at 8000 words]";
            }

            return $fullText;
        } catch (\Throwable $e) {
            return '<!-- Failed to load article text: ' . $e->getMessage() . ' -->';
        }
    }

    private function buildLockedDecisions(WizardSession $session, int $currentStep): string
    {
        $sections = [];

        $stepMap = [
            1 => ['key' => 'step1_brief', 'label' => 'Step 1 — Brief'],
            2 => ['key' => 'step2_structure', 'label' => 'Step 2 — Structure'],
            3 => ['key' => 'step3_article_selection', 'label' => 'Step 3 — Article Selection'],
            4 => ['key' => 'step4_analyses', 'label' => 'Step 4 — Article Analyses'],
            5 => ['key' => 'step5_directions', 'label' => 'Step 5 — Design Directions'],
            6 => ['key' => 'step6_thumbnails', 'label' => 'Step 6 — Thumbnails'],
        ];

        foreach ($stepMap as $step => $info) {
            if ($step >= $currentStep) break;

            $data = $session->{$info['key']};
            if ($data === null || $data === []) continue;

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $sections[] = "[{$info['label']}]\n{$json}";
        }

        return implode("\n\n", $sections);
    }

    private function loadFile(string $filename): string
    {
        $path = $this->promptDir . '/' . $filename;

        if (!file_exists($path)) {
            return "<!-- Prompt file missing: {$filename} -->";
        }

        return file_get_contents($path);
    }
}
