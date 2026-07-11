<?php

namespace App\Services\ThemeWizard;

use App\Services\AI\AnthropicClient;
use App\Services\AI\SchemaRepairLoop;
use App\Services\IssueStudio\TokenBudget;

/**
 * Applies a conversational nudge ("warmer", "more contrast", "quieter
 * headings", "try a rounded feel") to the current token profile and returns an
 * edited profile — coherent, still original, still readable. Schema-forced,
 * validated, one repair round-trip, budget-charged.
 *
 * Uses the lighter chat model (edits, not vision). The design read is rewritten
 * to reflect the change so the UI can show what shifted.
 */
class ThemeNudgeEngine
{
    public function __construct(
        private AnthropicClient $client,
        private TokenProfileValidator $validator,
        private SchemaRepairLoop $repair,
        private TokenBudget $budget,
    ) {}

    private function model(): string
    {
        return (string) (config('cms.theme_wizard.chat_model')
            ?? config('cms.issue_studio.model_interview')
            ?? 'claude-sonnet-5');
    }

    private function systemBlocks(): array
    {
        $layouts = implode(', ', TokenProfileSchema::LAYOUTS);
        $guardrails = Guardrails::block();
        return [[
            'type' => 'text',
            'cache_control' => ['type' => 'ephemeral'],
            'text' => <<<PROMPT
You are the Stillopress theme designer, refining an existing theme profile from the user's plain-language feedback. You are given the CURRENT profile (JSON) and a short instruction. Return the FULL updated profile JSON (same schema), changing only what the feedback implies and keeping everything else coherent.

{$guardrails}

Task specifics:
- Make the smallest change that satisfies the feedback, then re-balance so the result still reads as one design. "Warmer" shifts hues toward reds/ambers and softens neutrals; "more contrast" separates text from background and strengthens the brand; "quieter headings" lowers heading weight and/or scale; "rounder" moves radius toward soft/rounded; "airier" increases spacing density.
- You may change the layout personality only if the feedback clearly asks for a different structure. Layouts: {$layouts}.
- Rewrite design_read (2-3 sentences) to describe the theme AS IT NOW IS, noting what your change did.

Return ONLY the JSON object.
PROMPT,
        ]];
    }

    /**
     * @return array{profile: array, usages: array<int,array>}
     */
    public function nudge(string $tenantId, array $currentProfile, string $instruction): array
    {
        $this->budget->assertAvailable($tenantId);

        $messages = [[
            'role' => 'user',
            'content' => "CURRENT PROFILE:\n" . json_encode($currentProfile, JSON_PRETTY_PRINT)
                . "\n\nFEEDBACK: \"" . mb_substr($instruction, 0, 400) . "\"\n\nReturn the full updated profile JSON.",
        ]];

        $system = $this->systemBlocks();
        $model = $this->model();
        $schema = TokenProfileSchema::schema();

        $out = $this->repair->run(
            fn (array $msgs) => $this->client->complete($model, $system, $msgs, 2048, $schema),
            fn ($decoded) => $this->validator->validate($decoded),
            $messages,
        );

        foreach ($out['usages'] as $usage) {
            $this->budget->record($tenantId, $usage);
        }

        return ['profile' => $out['data'], 'usages' => $out['usages']];
    }
}
