<?php

namespace App\Services\IssueStudio;

use App\Models\IssueStudio\StudioSession;
use App\Models\IssueStudio\StudioSpread;
use RuntimeException;

/**
 * Opus-driven spread generation and conversational revision. Output is the
 * SpreadElementContract JSON (schema-forced), hard-validated, with one
 * automatic repair round-trip — same strategy as the flatplan engine.
 */
class SpreadEngine
{
    public function __construct(
        private AnthropicGateway $gateway,
        private Playbook $playbook,
        private SpreadValidator $validator,
    ) {
    }

    /**
     * @return array{doc: array, usage: array[]}
     */
    public function generate(StudioSession $session, StudioSpread $spread): array
    {
        $prompt = $this->basePrompt($session, $spread)
            . "\nDesign this "
            . ($spread->position === 0 ? 'COVER (one single page)' : 'SPREAD (left + right pages)')
            . " now, following the \"{$spread->pattern}\" pattern from spread-patterns.md.";

        return $this->run($session, $spread, $prompt);
    }

    /**
     * Conversational edit of the existing spread document.
     *
     * @param array $currentPages current pages+elements in contract form
     * @return array{doc: array, usage: array[]}
     */
    public function revise(StudioSession $session, StudioSpread $spread, array $currentPages, string $instruction): array
    {
        $prompt = $this->basePrompt($session, $spread)
            . "\nCURRENT SPREAD DOCUMENT (your earlier design):\n"
            . json_encode($currentPages, JSON_UNESCAPED_UNICODE)
            . "\n\nUSER'S REVISION REQUEST: {$instruction}\n\n"
            . 'Edit the existing design to honour the request — change what must change, keep what works. '
            . 'Return the complete revised document (all pages, all elements) plus a fresh editorial_note.';

        return $this->run($session, $spread, $prompt);
    }

    private function run(StudioSession $session, StudioSpread $spread, string $prompt): array
    {
        $brief = $session->brief ?? [];
        $isCover = $spread->position === 0;
        $usages = [];
        $errors = [];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $result = $this->gateway->complete(
                (string) config('cms.issue_studio.model_generate', 'claude-opus-4-8'),
                $this->systemBlocks($brief),
                [['role' => 'user', 'content' => $attempt === 0
                    ? $prompt
                    : $prompt . "\n\nYour previous design failed validation:\n- " . implode("\n- ", $errors) . "\nFix every point and return the complete corrected document."]],
                32000,
                SpreadElementContract::schema(),
            );
            $usages[] = $result['usage'];

            $doc = json_decode($result['text'], true);
            if (!is_array($doc)) {
                $errors = ['Response was not a JSON object.'];
                continue;
            }

            $errors = $this->validator->validate($doc, $isCover, $brief);
            if ($errors === []) {
                return ['doc' => $doc, 'usage' => $usages];
            }
        }

        throw new RuntimeException('Spread generation failed validation twice: ' . implode(' | ', array_slice($errors, 0, 6)));
    }

    private function systemBlocks(array $brief): array
    {
        $docs = $this->playbook->docsForGenre($brief['genre'] ?? null, ['spread-patterns']);
        $blocks = $this->playbook->systemBlocks($docs);
        // layout contract is stable too — merge into the cached block
        $blocks[0]['text'] .= "\n\n" . SpreadElementContract::layoutBrief();

        return $blocks;
    }

    private function basePrompt(StudioSession $session, StudioSpread $spread): string
    {
        $brief = $session->brief ?? [];

        $assigned = [];
        foreach ($brief['materials'] ?? [] as $m) {
            if (!in_array($m['id'] ?? '', $spread->materials ?? [], true)) {
                continue;
            }
            $entry = [
                'id' => $m['id'],
                'kind' => $m['kind'] ?? 'text',
                'title' => $m['title'] ?? '',
            ];
            if (($m['kind'] ?? '') === 'image') {
                $entry['note'] = 'image material — reference by material_id';
            } else {
                $entry['full_text'] = (string) ($m['content'] ?? '');
            }
            $assigned[] = $entry;
        }

        return "THE BRIEF:\n" . json_encode(array_diff_key($brief, ['materials' => null]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nTHE FLATPLAN (for rhythm context — you are designing one slot of it):\n"
            . json_encode($session->flatplan['spreads'] ?? [], JSON_UNESCAPED_UNICODE)
            . "\n\nTHIS SLOT:\n" . json_encode([
                'position' => $spread->position,
                'working_title' => $spread->working_title,
                'section' => $spread->section,
                'pattern' => $spread->pattern,
                'intent' => $spread->intent,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nASSIGNED MATERIALS (full text included — excerpt editorially, never overset):\n"
            . json_encode($assigned, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
}
