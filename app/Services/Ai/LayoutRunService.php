<?php

namespace App\Services\Ai;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\IssueComposer\Models\MagazineCurationRun;
use App\Services\AiService;
use App\Services\Magazine\LayoutEngine;
use App\Services\Magazine\TemplateRegistry;
use Illuminate\Support\Facades\Auth;

class LayoutRunService
{
    public function __construct(
        private AiService $aiService,
        private TemplateRegistry $templateRegistry,
        private LayoutEngine $layoutEngine,
    ) {}

    public function run(MagazineIssue $issue): MagazineCurationRun
    {
        $curation = $issue->curation_final;

        // Fallback: use latest curation run output if curation_final not saved
        if (!$curation) {
            $latestRun = MagazineCurationRun::where('issue_id', $issue->id)
                ->where('phase', 'curation')
                ->orderByDesc('created_at')
                ->first();
            $curation = $latestRun?->output_jsonb;
        }

        // Still nothing? Auto-generate a basic curation from items
        if (!$curation) {
            $issue->load('contentItems');
            $allIds = $issue->contentItems->pluck('id')->toArray();
            $curation = [
                'decisions' => array_map(fn($id) => ['item_id' => $id, 'decision' => 'kept', 'reason' => 'Auto-included'], $allIds),
                'sections' => [['id' => 'sec_1', 'title' => $issue->title ?: 'Main', 'one_line_description' => '', 'emotional_register' => 'informative', 'item_ids' => $allIds]],
                'flow' => [['section_id' => 'sec_1', 'density' => 'text_heavy', 'position' => 1]],
                'gaps' => [],
            ];
            // Save it so we don't regenerate next time
            $issue->update(['curation_final' => $curation]);
        }

        $issue->load('contentItems');
        $templates = $this->templateRegistry->toolAllowlist();
        $keptItems = $this->getKeptItems($issue, $curation);
        $pageCount = $issue->target_page_count;

        // Build prompt for Claude to assign templates + fill slots
        $systemPrompt = "You are a magazine layout designer. For each page, pick a template and fill its slots with content from the provided items. Return a JSON array of page specs.";

        $userPrompt = json_encode([
            'brief' => ['title' => $issue->title, 'theme' => $issue->theme, 'target_pages' => $pageCount],
            'sections' => $curation['sections'] ?? [],
            'flow' => $curation['flow'] ?? [],
            'items' => $keptItems,
            'templates' => $templates,
        ], JSON_PRETTY_PRINT);

        $userPrompt .= "\n\nGenerate exactly {$pageCount} pages. Return JSON array: [{page_number, section_id, template_id, density, slots: {slot_name: value}}]";

        // Call AI or generate fallback
        try {
            if (!$this->aiService->isAvailable()) {
                throw new \RuntimeException('AI not configured — using fallback');
            }
            $response = $this->aiService->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], ['model' => 'claude-sonnet-4-6', 'max_tokens' => 8192, 'temperature' => 0.5]);

            $pages = $this->parseLayoutResponse($response);
            $inputTokens = $response['usage']['input_tokens'] ?? 0;
            $outputTokens = $response['usage']['output_tokens'] ?? 0;
        } catch (\Throwable $e) {
            $pages = $this->generateFallbackLayout($issue, $curation, $keptItems);
            $inputTokens = 0;
            $outputTokens = 0;
        }

        $inputHash = hash('sha256', json_encode([$issue->curation_final, $issue->target_page_count]));

        $run = MagazineCurationRun::create([
            'issue_id' => $issue->id,
            'phase' => 'layout',
            'input_hash' => $inputHash,
            'claude_model' => 'claude-sonnet-4-6',
            'claude_input_tokens' => $inputTokens,
            'claude_output_tokens' => $outputTokens,
            'output_jsonb' => ['pages' => $pages],
            'prompt_version' => 'v1.0.0',
            'created_by' => Auth::id(),
        ]);

        // Store as layout_final
        $issue->update(['layout_final' => $pages]);

        return $run;
    }

    private function getKeptItems(MagazineIssue $issue, array $curation): array
    {
        $keptIds = collect($curation['decisions'] ?? [])
            ->where('decision', 'kept')
            ->pluck('item_id')
            ->toArray();

        $items = [];
        foreach ($issue->contentItems as $item) {
            if (!in_array($item->id, $keptIds)) {
                continue;
            }

            $entry = ['id' => $item->id, 'source_type' => $item->source_type];

            if ($item->source_type === 'post' && $item->source_id) {
                $post = \App\Models\Post::find($item->source_id);
                if ($post) {
                    $entry['title'] = $post->title;
                    $entry['excerpt'] = $post->excerpt;
                    // Get body from blocks (first 1500 words)
                    $blocks = $post->blocks()
                        ->whereIn('type', ['text', 'paragraph'])
                        ->orderBy('order')
                        ->get();
                    $body = '';
                    foreach ($blocks as $b) {
                        $body .= strip_tags($b->data['content'] ?? '') . ' ';
                        if (str_word_count($body) > 1500) {
                            break;
                        }
                    }
                    $entry['body'] = trim($body);
                    $entry['featured_image'] = $post->featured_image;
                }
            } elseif ($item->source_type === 'extra_text') {
                $entry['text'] = $item->extra_payload['text'] ?? '';
                $entry['caption'] = $item->extra_payload['caption'] ?? '';
            }

            $items[] = $entry;
        }

        return $items;
    }

    private function parseLayoutResponse(array $response): array
    {
        $content = $response['content'] ?? '';

        if (is_array($content)) {
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $content = $block['text'];
                    break;
                }
            }
        }

        $json = json_decode($content, true);
        if (is_array($json)) {
            // Could be wrapped in {pages: [...]} or direct array
            return $json['pages'] ?? $json;
        }

        if (preg_match('/```(?:json)?\s*(\[.*?\])\s*```/s', $content, $m)) {
            return json_decode($m[1], true) ?: [];
        }

        return [];
    }

    private function generateFallbackLayout(MagazineIssue $issue, array $curation, array $keptItems): array
    {
        // Use the intelligent LayoutEngine instead of dumb template cycling
        $brief = [
            'title' => $issue->title,
            'subtitle' => $issue->subtitle,
            'theme' => $issue->theme,
            'intention' => $issue->intention,
        ];

        return $this->layoutEngine->compose($brief, $keptItems, $curation);
    }
}
