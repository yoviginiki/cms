<?php

namespace App\Services\ThemeWizard;

use App\Services\AI\AnthropicClient;
use App\Services\AI\SchemaRepairLoop;
use App\Services\IssueStudio\TokenBudget;

/**
 * The "from conversation" path (W4): no reference image — the user describes a
 * mood, industry, and a couple of admired adjectives, and this produces an
 * original token profile from words alone. Uses the lighter chat model,
 * schema-forced + validated + repaired, budget-charged.
 */
class ThemeConversationEngine
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
        return [[
            'type' => 'text',
            'cache_control' => ['type' => 'ephemeral'],
            'text' => <<<PROMPT
You are the Stillopress theme designer. From a short description of what a user wants — mood, industry, a couple of admired adjectives — design an ORIGINAL theme as a design-token profile (JSON, per the enforced schema).

Rules:
- Design for the described FEELING and audience. Choose a palette (with roles), type described by CHARACTER (not a font name — the platform substitutes an open font), a type scale, spacing density, radius and shadow character, and the layout personality from: {$layouts}, that best fits.
- Make it READABLE (body text clearly contrasts the background) and DISTINCT (a visible brand and a separate accent).
- Give it a short evocative name and a two–three sentence design_read describing the feel and your key choices.
- If the description is thin, make confident, tasteful defaults rather than asking questions.

Return ONLY the JSON object.
PROMPT,
        ]];
    }

    /**
     * @return array{profile: array, usages: array<int,array>}
     */
    public function design(string $tenantId, string $description): array
    {
        $this->budget->assertAvailable($tenantId);

        $messages = [[
            'role' => 'user',
            'content' => 'Design a theme for: ' . mb_substr(trim($description), 0, 800),
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
