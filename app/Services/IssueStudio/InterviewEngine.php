<?php

namespace App\Services\IssueStudio;

use App\Models\IssueStudio\StudioSession;

/**
 * The Sonnet-driven interview. Structured but conversational: a fixed set
 * of things to learn, gathered through natural chat with aggressive
 * defaulting — the user is not an editor and should never have to work.
 */
class InterviewEngine
{
    public function __construct(
        private AnthropicGateway $gateway,
    ) {
    }

    /**
     * @return array{reply: string, patch: array, complete: bool, usage: array}
     */
    public function turn(StudioSession $session, string $userMessage): array
    {
        $model = (string) config('cms.issue_studio.model_interview', 'claude-sonnet-5');

        $messages = [];
        foreach (array_slice($session->transcript ?? [], -30) as $entry) {
            $messages[] = [
                'role' => $entry['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $entry['text'],
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $result = $this->gateway->complete(
            $model,
            $this->systemBlocks($session),
            $messages,
            2048,
            $this->responseSchema(),
        );

        $decoded = json_decode($result['text'], true);
        if (!is_array($decoded)) {
            // Structured outputs make this near-impossible; degrade gracefully.
            $decoded = ['reply' => $result['text'], 'brief_patch' => [], 'interview_complete' => false];
        }

        return [
            'reply' => (string) ($decoded['reply'] ?? ''),
            'patch' => is_array($decoded['brief_patch'] ?? null) ? $decoded['brief_patch'] : [],
            'complete' => (bool) ($decoded['interview_complete'] ?? false),
            'usage' => $result['usage'],
        ];
    }

    private function systemBlocks(StudioSession $session): array
    {
        // Static persona block first (cacheable), volatile session state after.
        $static = <<<'PROMPT'
You are the editorial director of Issue Studio, a magazine-creation wizard. You are
interviewing a user to build a creative brief for a magazine issue. The user is NOT an
editor — they want a beautiful magazine with as little work as possible. Behave like a
warm, decisive professional who does the thinking for them.

THINGS YOU MUST LEARN (the brief):
- topic: what the issue is about
- working_title: a title (PROPOSE one yourself early — never ask them to invent one)
- audience: who reads it (accept vague answers; sharpen them yourself)
- tone: the register (propose 2-3 words, e.g. "warm, curious")
- genre: exactly one of: politics, art-culture, business, lifestyle, interview
  (infer it from the topic whenever possible; only ask if genuinely ambiguous)
- page_ambition: rough page count (or null — the flatplan sizes from material anyway)
- materials: texts/images the user provides via the app (you see the inventory below;
  encourage them to paste texts and drop images, but a topic alone is enough to start)

RULES OF THE INTERVIEW:
- ONE question per turn, maximum. Short, plain, friendly. No jargon.
- Every question comes with a proposed default the user can accept by saying "yes".
- If an answer is vague, decide for them and state your choice in one sentence.
- Never re-ask something already in the brief state below.
- As soon as topic + genre are known (learned or confidently inferred), tell the user
  you have enough to plan the issue and ask if they want to add material or just go.
- Set interview_complete=true when the user signals to proceed (e.g. "go", "just do
  it", accepting your "shall we plan it?" offer) — fill remaining gaps with your own
  best defaults in the same brief_patch.
- Write in the user's language if they don't write English.

OUTPUT CONTRACT (JSON):
- reply: your conversational message to the user.
- brief_patch: fields you learned or decided THIS turn (null = no change). Include
  inferred/defaulted values, not just literal answers.
- interview_complete: true only per the rule above.
PROMPT;

        $brief = $session->brief ?? [];
        $materials = array_map(fn ($m) => [
            'id' => $m['id'] ?? '',
            'kind' => $m['kind'] ?? 'text',
            'title' => $m['title'] ?? '',
            'word_count' => $m['word_count'] ?? null,
        ], $brief['materials'] ?? []);

        $state = json_encode([
            'brief_so_far' => array_diff_key($brief, ['materials' => null]),
            'material_inventory' => $materials,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            ['type' => 'text', 'text' => $static, 'cache_control' => ['type' => 'ephemeral']],
            ['type' => 'text', 'text' => "CURRENT SESSION STATE:\n" . $state],
        ];
    }

    private function responseSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['reply', 'brief_patch', 'interview_complete'],
            'properties' => [
                'reply' => ['type' => 'string'],
                'interview_complete' => ['type' => 'boolean'],
                'brief_patch' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['topic', 'working_title', 'audience', 'tone', 'genre', 'page_ambition', 'note'],
                    'properties' => [
                        'topic' => $nullableString,
                        'working_title' => $nullableString,
                        'audience' => $nullableString,
                        'tone' => $nullableString,
                        'genre' => ['anyOf' => [
                            ['type' => 'string', 'enum' => Playbook::GENRES],
                            ['type' => 'null'],
                        ]],
                        'page_ambition' => ['type' => ['integer', 'null']],
                        'note' => $nullableString,
                    ],
                ],
            ],
        ];
    }
}
