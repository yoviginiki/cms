<?php

namespace App\Domain\Ai\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentAssistant
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = config('cms.ai.enabled', false);
        $this->apiKey = config('cms.ai.api_key') ?? '';
        $this->model = config('cms.ai.model', 'claude-sonnet-4-20250514');
        $this->maxTokens = (int) config('cms.ai.max_tokens', 2000);
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Generate new text content.
     */
    public function generateText(string $prompt, array $context = []): string
    {
        $this->ensureEnabled();

        $systemPrompt = "You are a content writer. Generate clean, semantic HTML suitable for a CMS text block. "
            . "No <script>, no <style>, no classes. Use only: <p>, <h2>-<h6>, <strong>, <em>, <a>, <ul>, <ol>, <li>, <blockquote>. "
            . "Match the tone and language of the existing content.";

        $contextText = '';
        if (!empty($context)) {
            $contextText = "\n\nExisting page content for reference:\n" . implode("\n", array_map(
                fn($b) => strip_tags($b['data']['content'] ?? $b['data']['text'] ?? ''),
                $context
            ));
        }

        return $this->callApi($systemPrompt, $prompt . $contextText);
    }

    /**
     * Rewrite existing content with an instruction.
     */
    public function rewrite(string $content, string $instruction, array $context = []): string
    {
        $this->ensureEnabled();

        $systemPrompt = "You are a content editor. Rewrite the given HTML content following the instruction. "
            . "Preserve the HTML structure and formatting tags. Return only the rewritten HTML, no explanations.";

        $prompt = "Instruction: {$instruction}\n\nContent to rewrite:\n{$content}";

        return $this->callApi($systemPrompt, $prompt);
    }

    /**
     * Translate content to target language.
     */
    public function translate(string $content, string $targetLanguage): string
    {
        $this->ensureEnabled();

        $systemPrompt = "You are a professional translator. Translate the given HTML content to {$targetLanguage}. "
            . "Preserve all HTML tags and structure exactly. Return only the translated HTML.";

        return $this->callApi($systemPrompt, "Translate to {$targetLanguage}:\n\n{$content}");
    }

    /**
     * Generate SEO metadata from page content.
     */
    public function generateSeoMeta(array $pageBlocks, string $siteName): array
    {
        $this->ensureEnabled();

        $content = collect($pageBlocks)->map(fn($b) => strip_tags(
            $b['data']['content'] ?? $b['data']['text'] ?? $b['data']['title'] ?? ''
        ))->filter()->implode("\n\n");

        $systemPrompt = "You are an SEO specialist. Analyze the page content and generate SEO metadata. "
            . "Return ONLY a JSON object with these fields: title (max 60 chars), description (max 160 chars), "
            . "og_title (max 60 chars), og_description (max 200 chars). No markdown, no explanations.";

        $result = $this->callApi($systemPrompt, "Site name: {$siteName}\n\nPage content:\n{$content}");

        $parsed = json_decode($result, true);
        if (!$parsed) {
            // Try to extract JSON from response
            if (preg_match('/\{[^}]+\}/s', $result, $m)) {
                $parsed = json_decode($m[0], true);
            }
        }

        return $parsed ?: [];
    }

    /**
     * Generate alt text for an image using vision.
     */
    public function suggestAltText(string $imageUrl): string
    {
        $this->ensureEnabled();

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 200,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => ['type' => 'url', 'url' => $imageUrl],
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Describe this image in a single, concise sentence suitable for an HTML alt attribute. Focus on what the image shows for accessibility. Return only the alt text, no quotes or explanations.',
                        ],
                    ],
                ],
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['content'][0]['text'] ?? '';
        }

        Log::warning('AI alt text generation failed', ['status' => $response->status()]);
        throw new \RuntimeException('Failed to generate alt text');
    }

    private function callApi(string $systemPrompt, string $userMessage): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['content'][0]['text'] ?? '';
        }

        Log::warning('AI API call failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException('AI service is temporarily unavailable. Please try again.');
    }

    private function ensureEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('AI features are not enabled. Set AI_ENABLED=true and ANTHROPIC_API_KEY in .env');
        }
    }
}
