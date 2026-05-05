<?php

namespace App\Services\Magazine;

use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AnthropicClient
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct(?string $apiKey = null, string $model = 'claude-sonnet-4-6')
    {
        $this->apiKey = $apiKey ?: (string) env('ANTHROPIC_API_KEY');

        // Fallback: check site settings if env is empty
        if (empty($this->apiKey)) {
            $this->apiKey = $this->getKeyFromSiteSettings();
        }

        $this->model = $model;
    }

    private function getKeyFromSiteSettings(): string
    {
        try {
            $user = Auth::user();
            if (!$user) return '';
            $site = Site::first();
            if (!$site) return '';
            return $site->settings['anthropic_api_key'] ?? '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function isAvailable(): bool
    {
        // Re-check site settings in case key was added after construction
        if (empty($this->apiKey)) {
            $this->apiKey = $this->getKeyFromSiteSettings();
        }
        return !empty($this->apiKey);
    }

    /**
     * Stream a message from the Anthropic API.
     *
     * @param string $systemPrompt
     * @param array $messages  Array of ['role' => 'user'|'assistant', 'content' => '...']
     * @param callable $onDelta  fn(string $textChunk): void
     * @param callable $onComplete fn(array{full_text: string, tokens_in: int, tokens_out: int}): void
     * @param callable|null $onError fn(string $errorMessage): void
     */
    public function streamMessage(
        string $systemPrompt,
        array $messages,
        callable $onDelta,
        callable $onComplete,
        ?callable $onError = null,
        int $maxRetries = 2,
    ): void {
        $payload = json_encode([
            'model' => $this->model,
            'max_tokens' => 8192,
            'temperature' => 0.7,
            'stream' => true,
            'system' => $systemPrompt,
            'messages' => $messages,
        ]);

        $attempt = 0;
        while ($attempt <= $maxRetries) {
            try {
                $this->doStream($payload, $onDelta, $onComplete);
                return;
            } catch (AnthropicOverloadException $e) {
                $attempt++;
                if ($attempt > $maxRetries) {
                    $msg = 'Anthropic API overloaded after ' . $maxRetries . ' retries';
                    Log::warning($msg);
                    if ($onError) $onError($msg);
                    return;
                }
                // Exponential backoff: 1s, 2s
                usleep($attempt * 1_000_000);
            } catch (\Throwable $e) {
                $msg = 'Anthropic API error: ' . $e->getMessage();
                Log::error($msg, ['exception' => $e]);
                if ($onError) $onError($msg);
                return;
            }
        }
    }

    private function doStream(string $payload, callable $onDelta, callable $onComplete): void
    {
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'Accept: text/event-stream',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $fullText = '';
        $tokensIn = 0;
        $tokensOut = 0;
        $httpCode = 0;
        $buffer = '';

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$httpCode) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $m)) {
                $httpCode = (int) $m[1];
            }
            return strlen($header);
        });

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer, &$fullText, &$tokensIn, &$tokensOut, &$httpCode, $onDelta) {
            if ($httpCode >= 400) {
                $buffer .= $data;
                return strlen($data);
            }

            $buffer .= $data;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines); // keep incomplete line

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, ':')) continue;
                if (!str_starts_with($line, 'data: ')) continue;

                $json = json_decode(substr($line, 6), true);
                if (!$json) continue;

                $type = $json['type'] ?? '';

                if ($type === 'content_block_delta') {
                    $text = $json['delta']['text'] ?? '';
                    if ($text !== '') {
                        $fullText .= $text;
                        $onDelta($text);
                    }
                } elseif ($type === 'message_start') {
                    $tokensIn = $json['message']['usage']['input_tokens'] ?? 0;
                } elseif ($type === 'message_delta') {
                    $tokensOut = $json['usage']['output_tokens'] ?? $tokensOut;
                }
            }

            return strlen($data);
        });

        curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 529) {
            throw new AnthropicOverloadException('API overloaded (529)');
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("Anthropic API returned HTTP {$httpCode}: " . mb_substr($buffer, 0, 500));
        }

        if ($curlError) {
            throw new \RuntimeException("cURL error: {$curlError}");
        }

        $onComplete([
            'full_text' => $fullText,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
        ]);
    }
}

class AnthropicOverloadException extends \RuntimeException {}
