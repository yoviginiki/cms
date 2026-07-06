<?php

namespace App\Services\IssueStudio;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Issue Studio's Anthropic Messages API client.
 *
 * Raw HTTP (matches the codebase's existing AI clients), with prompt caching
 * via cache_control on system blocks and full usage capture including cache
 * read/write tokens.
 */
class AnthropicGateway
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) (config('cms.ai.api_key') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param array $systemBlocks [['type'=>'text','text'=>..., 'cache_control'=>?], ...]
     * @param array $messages     [['role'=>'user'|'assistant','content'=>string], ...]
     * @return array{text: string, usage: array{input:int, output:int, cache_write:int, cache_read:int, model:string}}
     */
    public function complete(string $model, array $systemBlocks, array $messages, int $maxTokens = 4096, ?array $jsonSchema = null): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('AI is not configured (ANTHROPIC_API_KEY missing).');
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemBlocks,
            'messages' => $messages,
        ];

        if ($jsonSchema !== null) {
            $payload['output_config'] = ['format' => ['type' => 'json_schema', 'schema' => $jsonSchema]];
        }

        try {
            $response = $this->post($payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('IssueStudio: Anthropic connection failure, retrying once', ['error' => $e->getMessage()]);
            sleep(3);
            try {
                $response = $this->post($payload);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                throw new RuntimeException('AI request timed out twice — try again in a moment.');
            }
        }

        if ($response->status() === 429 || $response->status() >= 500) {
            // one retry after backoff for transient failures
            sleep(2);
            $response = $this->post($payload);
        }

        if (!$response->successful()) {
            $err = $response->json('error.message') ?? $response->body();
            Log::warning('IssueStudio: Anthropic API error', ['status' => $response->status(), 'error' => $err]);
            throw new RuntimeException('AI request failed: ' . mb_substr((string) $err, 0, 300));
        }

        $data = $response->json();

        if (($data['stop_reason'] ?? null) === 'refusal') {
            throw new RuntimeException('The AI declined this request. Rephrase and try again.');
        }

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        $usage = $data['usage'] ?? [];

        return [
            'text' => $text,
            'usage' => [
                'input' => (int) ($usage['input_tokens'] ?? 0),
                'output' => (int) ($usage['output_tokens'] ?? 0),
                'cache_write' => (int) ($usage['cache_creation_input_tokens'] ?? 0),
                'cache_read' => (int) ($usage['cache_read_input_tokens'] ?? 0),
                'model' => $model,
            ],
        ];
    }

    private function post(array $payload): Response
    {
        return Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(480)->post('https://api.anthropic.com/v1/messages', $payload);
    }
}
