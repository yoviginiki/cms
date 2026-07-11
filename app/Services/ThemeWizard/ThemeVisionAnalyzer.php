<?php

namespace App\Services\ThemeWizard;

use App\Services\AI\AnthropicClient;
use App\Services\AI\SchemaRepairLoop;
use App\Services\IssueStudio\TokenBudget;

/**
 * Turns a reference image (screenshot of a site the user likes) into a
 * validated token profile via an Opus vision call.
 *
 * The system prompt is the "inspired original, not a copy" guardrail (W5
 * hardens it further): extract the FEEL as design tokens, shift hues, describe
 * type by character so we substitute open fonts, and never reproduce imagery,
 * logos, or copy. Output is schema-forced and semantically validated, with one
 * repair round-trip; the tenant's token budget is charged for every attempt.
 */
class ThemeVisionAnalyzer
{
    public function __construct(
        private AnthropicClient $client,
        private TokenProfileValidator $validator,
        private SchemaRepairLoop $repair,
        private TokenBudget $budget,
    ) {}

    private function model(): string
    {
        return (string) (config('cms.theme_wizard.vision_model')
            ?? config('cms.issue_studio.model_generate')
            ?? 'claude-opus-4-8');
    }

    private function systemBlocks(): array
    {
        $layouts = implode(', ', TokenProfileSchema::LAYOUTS);
        $guardrails = Guardrails::block();
        return [[
            'type' => 'text',
            'cache_control' => ['type' => 'ephemeral'],
            'text' => <<<PROMPT
You are the Stillopress theme designer. You are shown a screenshot of a website whose FEEL a user admires. Produce a design-token PROFILE (JSON, per the enforced schema) for an ORIGINAL theme that clearly feels related but is distinct — never a copy.

{$guardrails}

Task specifics:
- PALETTE ROLES: read the true background/canvas, a subtle surface tone, body text, heading, muted text, hairline border, the primary brand/link color, and a distinct secondary accent.
- CHOOSE THE LAYOUT PERSONALITY that best matches the reference from: {$layouts}. (cinematic = full-bleed image-forward & minimal; magazine = editorial/serif/columns; business = SaaS/marketing/structured; portfolio = image-led gallery; lifestyle = warm/rounded/soft; standard = general.)
- Pick scale (compact/balanced/dramatic), spacing density (tight/balanced/airy), radius character (sharp/soft/rounded), and shadow (none/subtle/soft) to match what you see.
- Give the theme a short evocative name and a two–three sentence "design_read" explaining the feel and what makes your version its own.

Return ONLY the JSON object.
PROMPT,
        ]];
    }

    /**
     * @return array{profile: array, usages: array<int,array>}
     */
    public function analyze(string $tenantId, string $imageBase64, string $mediaType, ?string $hint = null): array
    {
        $this->budget->assertAvailable($tenantId);

        $text = 'Analyze this reference and produce the token profile.';
        if ($hint) {
            $text .= ' The user adds: "' . mb_substr($hint, 0, 300) . '". Honor it while keeping the design original.';
        }

        $messages = [[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => $imageBase64]],
                ['type' => 'text', 'text' => $text],
            ],
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
