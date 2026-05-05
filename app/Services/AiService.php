<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    private ?string $apiKey;

    public function __construct()
    {
        // Try: env var → site settings → null
        $this->apiKey = env('ANTHROPIC_API_KEY');

        if (!$this->apiKey) {
            $this->apiKey = $this->getKeyFromSiteSettings();
        }
    }

    private function getKeyFromSiteSettings(): ?string
    {
        try {
            $user = Auth::user();
            if (!$user) return null;

            $site = Site::where('tenant_id', $user->tenant_id)->first();
            if (!$site) return null;

            return $site->settings['anthropic_api_key'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function chat(array $messages, array $options = []): array
    {
        if (!$this->apiKey) {
            throw new \RuntimeException('No Anthropic API key configured. Go to Settings → AI to add your key.');
        }

        $model = $options['model'] ?? 'claude-sonnet-4-6';
        $maxTokens = $options['max_tokens'] ?? 4096;
        $temperature = $options['temperature'] ?? 0.7;

        $systemMessage = null;
        $userMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage = $msg['content'];
            } else {
                $userMessages[] = $msg;
            }
        }

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => $userMessages,
        ];

        if ($systemMessage) {
            $payload['system'] = $systemMessage;
        }

        $response = Http::timeout(120)
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            Log::error('Claude API error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Claude API error: ' . $response->status());
        }

        $data = $response->json();

        return [
            'content' => $data['content'] ?? [],
            'usage' => $data['usage'] ?? ['input_tokens' => 0, 'output_tokens' => 0],
        ];
    }

    public function isAvailable(): bool
    {
        if (!$this->apiKey) {
            // Re-check site settings (may have been saved after construction)
            $this->apiKey = $this->getKeyFromSiteSettings();
        }
        return !empty($this->apiKey);
    }
}
