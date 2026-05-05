<?php

namespace App\Services\Ai;

use App\Domain\IssueComposer\Models\MagazineIssue;
use App\Domain\IssueComposer\Models\MagazineCurationRun;
use App\Models\Post;
use App\Services\AiService;
use Illuminate\Support\Facades\Auth;

class CurationRunService
{
    public function __construct(private AiService $aiService) {}

    /**
     * Run AI curation on an issue's content items.
     * Returns a curation run with structured output.
     */
    public function run(MagazineIssue $issue, ?string $userDirective = null, array $lockedSectionIds = []): MagazineCurationRun
    {
        $issue->load('contentItems');

        // Build item manifest
        $manifest = $this->buildManifest($issue);
        $brief = $this->buildBrief($issue);

        // Build prompt
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($brief, $manifest, $userDirective, $lockedSectionIds);

        // Compute input hash for dedup
        $inputHash = hash('sha256', json_encode([$brief, $manifest, $userDirective, $lockedSectionIds]));

        // Check for existing run with same hash
        $existing = MagazineCurationRun::where('issue_id', $issue->id)
            ->where('phase', 'curation')
            ->where('input_hash', $inputHash)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Call Claude (or use fallback if no API key)
        try {
            if (!$this->aiService->isAvailable()) {
                throw new \RuntimeException('AI not configured — using fallback');
            }
            $response = $this->aiService->chat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], [
                'model' => 'claude-opus-4-7',
                'max_tokens' => 4096,
                'temperature' => 0.7,
            ]);

            $output = $this->parseResponse($response);
            $inputTokens = $response['usage']['input_tokens'] ?? 0;
            $outputTokens = $response['usage']['output_tokens'] ?? 0;
        } catch (\Throwable $e) {
            // Fallback: generate a basic curation from the items
            $output = $this->generateFallbackCuration($issue);
            $inputTokens = 0;
            $outputTokens = 0;
        }

        // Store the run
        $run = MagazineCurationRun::create([
            'issue_id' => $issue->id,
            'phase' => 'curation',
            'input_hash' => $inputHash,
            'claude_model' => 'claude-opus-4-7',
            'claude_input_tokens' => $inputTokens,
            'claude_output_tokens' => $outputTokens,
            'output_jsonb' => $output,
            'prompt_version' => 'v1.0.0',
            'created_by' => Auth::id(),
        ]);

        // Update item ai_decisions from the output
        $this->applyDecisions($issue, $output);

        return $run;
    }

    private function buildBrief(MagazineIssue $issue): array
    {
        return [
            'title' => $issue->title,
            'subtitle' => $issue->subtitle,
            'theme' => $issue->theme,
            'intention' => $issue->intention,
            'tone_knobs' => $issue->tone_knobs,
            'target_page_count' => $issue->target_page_count,
            'language' => $issue->language,
        ];
    }

    private function buildManifest(MagazineIssue $issue): array
    {
        $items = [];

        foreach ($issue->contentItems as $item) {
            $entry = [
                'id' => $item->id,
                'source_type' => $item->source_type,
                'importance' => $item->importance,
                'role_hint' => $item->role_hint,
                'editor_note' => $item->editor_note,
                'position' => $item->position,
            ];

            if ($item->source_type === 'post' && $item->source_id) {
                $post = Post::find($item->source_id);
                if ($post) {
                    $entry['title'] = $post->title;
                    $entry['slug'] = $post->slug;
                    $entry['excerpt'] = $post->excerpt;
                    $entry['category'] = $post->category?->name;
                    $entry['published_at'] = $post->published_at?->toDateString();
                    // Get word count from blocks
                    $blocks = $post->blocks()->get();
                    $wordCount = 0;
                    foreach ($blocks as $block) {
                        $content = $block->data['content'] ?? $block->data['text'] ?? '';
                        $wordCount += str_word_count(strip_tags($content));
                    }
                    $entry['word_count'] = $wordCount;
                    // Get opening paragraph
                    $firstBlock = $post->blocks()->whereIn('type', ['text', 'paragraph'])->orderBy('order')->first();
                    if ($firstBlock) {
                        $entry['opening'] = mb_substr(strip_tags($firstBlock->data['content'] ?? ''), 0, 300);
                    }
                }
            } elseif ($item->source_type === 'extra_text') {
                $entry['text'] = mb_substr($item->extra_payload['text'] ?? '', 0, 500);
                $entry['caption'] = $item->extra_payload['caption'] ?? null;
                $entry['word_count'] = str_word_count($item->extra_payload['text'] ?? '');
            } else {
                $entry['caption'] = $item->extra_payload['caption'] ?? null;
            }

            $items[] = $entry;
        }

        return $items;
    }

    private function buildSystemPrompt(): string
    {
        return <<<PROMPT
You are an experienced magazine editor. Your job is to take a collection of articles, images, and text pieces and curate them into a cohesive magazine issue.

You must return a JSON object with this exact structure:
{
  "decisions": [
    {"item_id": "uuid", "decision": "kept|dropped|trimmed", "reason": "why", "trim_note": "optional trim guidance"}
  ],
  "sections": [
    {"id": "sec_1", "title": "Section Title", "one_line_description": "what this section covers", "emotional_register": "contemplative|energetic|informative|playful", "item_ids": ["uuid1", "uuid2"]}
  ],
  "flow": [
    {"section_id": "sec_1", "density": "text_heavy|visual|break|reflection", "position": 1}
  ],
  "gaps": [
    {"description": "what's missing", "suggested_fix": "how to fill it"}
  ]
}

Rules:
- Items marked "must" importance MUST be kept. Never drop them.
- Respect role_hint when assigning sections (cover → first section, closing → last).
- Create 3-7 sections depending on content volume.
- Flow should alternate between text-heavy and visual/break sections for good pacing.
- If content is insufficient for the target page count, note gaps.
- Every kept item must appear in exactly one section.
- Return ONLY valid JSON, no markdown or explanation.
PROMPT;
    }

    private function buildUserPrompt(array $brief, array $manifest, ?string $directive, array $lockedSections): string
    {
        $prompt = "## Issue Brief\n" . json_encode($brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $prompt .= "\n\n## Content Items ({$this->countItems($manifest)} items)\n" . json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($directive) {
            $prompt .= "\n\n## Editor Directive\n{$directive}";
        }

        if (!empty($lockedSections)) {
            $prompt .= "\n\n## Locked Sections (do not modify)\n" . json_encode($lockedSections);
        }

        $prompt .= "\n\nCurate this content into a magazine issue. Return the JSON structure.";

        return $prompt;
    }

    private function countItems(array $manifest): int
    {
        return count($manifest);
    }

    private function parseResponse(array $response): array
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

        // Try to parse JSON from the response
        $json = json_decode($content, true);
        if ($json && isset($json['decisions'])) {
            return $json;
        }

        // Try to extract JSON from markdown code block
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $m)) {
            $json = json_decode($m[1], true);
            if ($json) return $json;
        }

        // Return empty structure
        return ['decisions' => [], 'sections' => [], 'flow' => [], 'gaps' => [['description' => 'AI response could not be parsed', 'suggested_fix' => 'Try running again']]];
    }

    /**
     * Generate a basic curation without AI (fallback).
     */
    private function generateFallbackCuration(MagazineIssue $issue): array
    {
        $items = $issue->contentItems;
        $decisions = [];
        $sectionItems = [];

        foreach ($items as $item) {
            $decisions[] = [
                'item_id' => $item->id,
                'decision' => 'kept',
                'reason' => 'Included by default (AI unavailable)',
            ];
            $sectionItems[] = $item->id;
        }

        return [
            'decisions' => $decisions,
            'sections' => [
                [
                    'id' => 'sec_1',
                    'title' => 'Main content',
                    'one_line_description' => 'All content items in submission order',
                    'emotional_register' => 'informative',
                    'item_ids' => $sectionItems,
                ],
            ],
            'flow' => [
                ['section_id' => 'sec_1', 'density' => 'text_heavy', 'position' => 1],
            ],
            'gaps' => [],
        ];
    }

    private function applyDecisions(MagazineIssue $issue, array $output): void
    {
        foreach ($output['decisions'] ?? [] as $decision) {
            $issue->contentItems()
                ->where('id', $decision['item_id'])
                ->update(['ai_decision' => $decision['decision']]);
        }
    }
}
