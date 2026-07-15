<?php

namespace App\Services\PageWizard;

use App\Services\AI\AnthropicClient;
use App\Services\AI\SchemaRepairLoop;
use App\Services\IssueStudio\TokenBudget;

/**
 * The Page Wizard's AI brain. Four entry points, one enforced block-manifest
 * schema + one semantic validator + one repair loop:
 *   - fromScreenshot: replicate a reference's LAYOUT (Opus vision)
 *   - fromContent:    lay a URL's extracted text/images into a page (Sonnet)
 *   - fromDescription: generate from a written brief (Sonnet)
 *   - nudge:          refine the current manifest (Sonnet)
 * Every attempt is charged against the tenant token budget.
 */
class PageWizardEngine
{
    public function __construct(
        private AnthropicClient $client,
        private PageManifestValidator $validator,
        private SchemaRepairLoop $repair,
        private TokenBudget $budget,
    ) {
    }

    private function visionModel(): string
    {
        return (string) (config('cms.page_wizard.vision_model') ?? 'claude-opus-4-8');
    }

    private function contentModel(): string
    {
        return (string) (config('cms.page_wizard.content_model') ?? 'claude-sonnet-5');
    }

    /**
     * @param array{data:string, media_type:string} $image
     * @return array{manifest: array, usages: array<int,array>}
     */
    public function fromScreenshot(string $tenantId, array $image, ?string $hint = null): array
    {
        $text = 'Rebuild this page\'s LAYOUT as a Stillopress page manifest — match the section order, hierarchy, and block types you see (hero, headings, text, image, gallery, columns, call-to-action). Use short, representative placeholder copy where you can\'t read exact text; do NOT reproduce logos or copyrighted imagery — leave image urls out unless clearly generic.';
        if ($hint) {
            $text .= ' The user adds: "' . mb_substr($hint, 0, 300) . '".';
        }

        $messages = [[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['media_type'], 'data' => $image['data']]],
                ['type' => 'text', 'text' => $text],
            ],
        ]];

        return $this->run($tenantId, $this->visionModel(), $messages);
    }

    /**
     * @param array{title:string, outline:string, images:array<int,string>} $content
     * @return array{manifest: array, usages: array<int,array>}
     */
    public function fromContent(string $tenantId, array $content, ?string $hint = null): array
    {
        $imagesBlock = $content['images'] === [] ? '(none)' : implode("\n", array_slice($content['images'], 0, 12));
        $text = <<<TXT
Lay this page's real content into a Stillopress page. Use the headings and paragraphs below as the actual copy (don't invent facts), give it a sensible structure (a hero from the top heading, sections for the rest, a columns block where there's a natural group of 2–3), and use the listed image URLs where they fit.

PAGE TITLE: {$content['title']}

CONTENT OUTLINE (H1–H6 = headings, "- " = list items, plain = paragraphs):
{$content['outline']}

AVAILABLE IMAGE URLS:
{$imagesBlock}
TXT;
        if ($hint) {
            $text .= "\n\nThe user adds: \"" . mb_substr($hint, 0, 300) . '".';
        }

        return $this->run($tenantId, $this->contentModel(), [['role' => 'user', 'content' => $text]]);
    }

    /**
     * @return array{manifest: array, usages: array<int,array>}
     */
    public function fromDescription(string $tenantId, string $description): array
    {
        $text = 'Design a page from this brief. Choose a sensible structure and write concise, real-sounding copy.' . "\n\nBRIEF: " . mb_substr($description, 0, 2000);

        return $this->run($tenantId, $this->contentModel(), [['role' => 'user', 'content' => $text]]);
    }

    /**
     * @return array{manifest: array, usages: array<int,array>}
     */
    public function nudge(string $tenantId, array $manifest, string $instruction): array
    {
        $current = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $text = "Here is the current page manifest:\n\n{$current}\n\nApply this change and return the FULL updated manifest: \"" . mb_substr($instruction, 0, 500) . '"';

        return $this->run($tenantId, $this->contentModel(), [['role' => 'user', 'content' => $text]]);
    }

    /**
     * @param array<int, array{role:string, content:mixed}> $messages
     * @return array{manifest: array, usages: array<int,array>}
     */
    private function run(string $tenantId, string $model, array $messages): array
    {
        $this->budget->assertAvailable($tenantId);

        $system = $this->systemBlocks();

        // NOTE: we deliberately do NOT use json_schema structured output here.
        // The manifest is a variable-length array of varied block objects, and
        // the Anthropic grammar compiler times out on that shape ("Grammar
        // compilation timed out"). Instead we prompt firmly for bare JSON and
        // rely on the SchemaRepairLoop (decode + semantic validate + one
        // repair round); a fence-stripping wrapper tolerates the occasional
        // ```json fence or stray prose.
        $out = $this->repair->run(
            function (array $msgs) use ($model, $system) {
                $res = $this->client->complete($model, $system, $msgs, 4096, null);
                $res['text'] = $this->extractJson($res['text'] ?? '');

                return $res;
            },
            fn ($decoded) => $this->validator->validate($decoded),
            $messages,
        );

        foreach ($out['usages'] as $usage) {
            $this->budget->record($tenantId, $usage);
        }

        return ['manifest' => $out['data'], 'usages' => $out['usages']];
    }

    /** Pull the JSON object out of a model reply (strips code fences / prose). */
    private function extractJson(string $text): string
    {
        $text = trim($text);
        // Strip a leading ```json / ``` fence and its closer.
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', trim($text));

        // Fall back to the outermost {...} span if there's surrounding prose.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }

    private function systemBlocks(): array
    {
        $kinds = implode(', ', PageManifestSchema::KINDS);

        return [[
            'type' => 'text',
            'cache_control' => ['type' => 'ephemeral'],
            'text' => <<<PROMPT
You are the Stillopress page designer. You output a PAGE MANIFEST as a single JSON object that becomes a real, editable web page.

The JSON object has exactly these top-level keys:
- "page_title": string
- "design_read": string (2–3 sentences describing the page)
- "blocks": an ordered array of block objects, each with a "kind" and the fields that kind uses:
  - hero: {kind, title, subtitle?, cta_text?, cta_url?, align?}
  - heading: {kind, text, level?}       // level = h1..h6
  - text: {kind, body}
  - image: {kind, url, alt?}            // http(s) url only
  - gallery: {kind, images:[url,...]}
  - button: {kind, text, url?}
  - cta: {kind, title, body?, cta_text?, cta_url?}
  - columns: {kind, columns:[{heading?, body?, image?}, ...]}   // 2 or 3 cells
  - divider: {kind}
  - spacer: {kind}

Available block kinds: {$kinds}.
- hero: a prominent top banner (needs a title; optional subtitle + one call-to-action button).
- heading / text: a section heading and its paragraph(s).
- image / gallery: a single image or a small image grid (http(s) urls only).
- button: a standalone call-to-action link.
- cta: a centered call-to-action band (title + optional body + button).
- columns: 2 or 3 side-by-side cells, each with an optional heading, body, and image — use for feature grids, service lists, team members.
- divider / spacer: light visual separation.

Rules:
- Start most pages with a hero. Keep copy concise and real-sounding.
- Only ever use http(s) image URLs that were given to you; never invent image URLs or embed data: URIs.
- Never reproduce logos, trademarks, or copyrighted text/imagery — capture the STRUCTURE and feel.
- Return ONLY the raw JSON object — no markdown code fences, no commentary before or after.
PROMPT,
        ]];
    }
}
