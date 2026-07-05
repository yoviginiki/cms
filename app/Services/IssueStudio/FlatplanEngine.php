<?php

namespace App\Services\IssueStudio;

use App\Models\IssueStudio\StudioSession;
use RuntimeException;

/**
 * Opus-driven flatplan generation and per-spread conversational revision.
 * The playbook rides as a cached system prefix; output is schema-forced
 * JSON, then hard-validated with one automatic repair round-trip.
 */
class FlatplanEngine
{
    public function __construct(
        private AnthropicGateway $gateway,
        private Playbook $playbook,
        private FlatplanValidator $validator,
    ) {
    }

    /**
     * @return array{spreads: array, usage: array[]} usage = one entry per API call
     */
    public function generate(StudioSession $session): array
    {
        $brief = $session->brief ?? [];
        $prompt = "Plan this issue now. Produce the complete flatplan as JSON per the output contract in flatplan.md.\n\n"
            . $this->briefContext($brief)
            . "\nRemember: size honestly from the material (round DOWN), cover at position 0, strongest feature second in the well, "
            . "end with closer-colophon, respect the rhythm-weight rules, and record every silent assumption in the intent lines.";

        return $this->generateWithRepair($session, $prompt);
    }

    /**
     * Conversational revision of one spread ("swap this to an image-led pattern").
     *
     * @return array{spread: array, usage: array[]}
     */
    public function reviseSpread(StudioSession $session, int $position, string $instruction): array
    {
        $spreads = $session->flatplan['spreads'] ?? [];
        $target = collect($spreads)->firstWhere('position', $position);
        if (!$target) {
            throw new RuntimeException("No spread at position {$position}.");
        }

        $brief = $session->brief ?? [];
        $prompt = "Revise ONE spread of an approved-in-progress flatplan.\n\n"
            . $this->briefContext($brief)
            . "\nCURRENT FLATPLAN:\n" . json_encode($spreads, JSON_UNESCAPED_UNICODE)
            . "\n\nSPREAD TO REVISE (position {$position}):\n" . json_encode($target, JSON_UNESCAPED_UNICODE)
            . "\n\nEDITOR'S INSTRUCTION: {$instruction}\n\n"
            . 'Return the revised spread object only. Keep position ' . $position . '. '
            . 'Choose patterns from spread-patterns.md only; material ids from the inventory only.';

        $usages = [];
        $errors = [];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $result = $this->gateway->complete(
                (string) config('cms.issue_studio.model_generate', 'claude-opus-4-8'),
                $this->systemBlocks($brief),
                [['role' => 'user', 'content' => $attempt === 0 ? $prompt : $prompt . "\n\nYour previous attempt failed validation:\n- " . implode("\n- ", $errors) . "\nFix these and return the spread again."]],
                4096,
                $this->spreadSchema(),
            );
            $usages[] = $result['usage'];

            $spread = json_decode($result['text'], true);
            if (!is_array($spread)) {
                $errors = ['Response was not a JSON object.'];
                continue;
            }
            $spread['position'] = $position;

            $errors = $this->validator->validateSingle($spread, $spreads, $brief);
            if ($errors === []) {
                return ['spread' => $spread, 'usage' => $usages];
            }
        }

        throw new RuntimeException('The revision failed validation twice: ' . implode(' | ', $errors));
    }

    private function generateWithRepair(StudioSession $session, string $prompt): array
    {
        $brief = $session->brief ?? [];
        $usages = [];
        $errors = [];

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $result = $this->gateway->complete(
                (string) config('cms.issue_studio.model_generate', 'claude-opus-4-8'),
                $this->systemBlocks($brief),
                [['role' => 'user', 'content' => $attempt === 0 ? $prompt : $prompt . "\n\nYour previous flatplan failed validation:\n- " . implode("\n- ", $errors) . "\nProduce a corrected complete flatplan."]],
                16000,
                $this->flatplanSchema(),
            );
            $usages[] = $result['usage'];

            $decoded = json_decode($result['text'], true);
            $spreads = is_array($decoded) ? ($decoded['spreads'] ?? null) : null;
            if (!is_array($spreads)) {
                $errors = ['Response did not contain a spreads array.'];
                continue;
            }

            $errors = $this->validator->validate($spreads, $brief);
            if ($errors === []) {
                return ['spreads' => array_values($spreads), 'usage' => $usages];
            }
        }

        throw new RuntimeException('Flatplan generation failed validation twice: ' . implode(' | ', $errors));
    }

    private function systemBlocks(array $brief): array
    {
        $docs = $this->playbook->docsForGenre($brief['genre'] ?? null, ['flatplan', 'spread-patterns']);

        return $this->playbook->systemBlocks($docs);
    }

    private function briefContext(array $brief): string
    {
        $materials = array_map(function ($m) {
            $entry = [
                'id' => $m['id'] ?? '',
                'kind' => $m['kind'] ?? 'text',
                'title' => $m['title'] ?? '',
            ];
            if (isset($m['word_count'])) {
                $entry['word_count'] = $m['word_count'];
            }
            if (($m['kind'] ?? '') !== 'image' && isset($m['content'])) {
                $entry['excerpt'] = mb_substr(strip_tags((string) $m['content']), 0, 400);
            }

            return $entry;
        }, $brief['materials'] ?? []);

        return "THE BRIEF:\n" . json_encode(array_diff_key($brief, ['materials' => null]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nMATERIAL INVENTORY:\n" . json_encode($materials, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }

    private function flatplanSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['spreads'],
            'properties' => [
                'spreads' => ['type' => 'array', 'items' => $this->spreadSchema()],
            ],
        ];
    }

    private function spreadSchema(): array
    {
        $patterns = $this->playbook->patternNames();

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['position', 'working_title', 'section', 'pattern', 'materials', 'intent'],
            'properties' => [
                'position' => ['type' => 'integer'],
                'working_title' => ['type' => 'string'],
                'section' => ['type' => 'string', 'enum' => ['cover', 'fob', 'feature', 'bob']],
                'pattern' => ['type' => 'string', 'enum' => array_merge($patterns['covers'], $patterns['spreads'])],
                'materials' => ['type' => 'array', 'items' => ['type' => 'string']],
                'intent' => ['type' => 'string'],
            ],
        ];
    }
}
