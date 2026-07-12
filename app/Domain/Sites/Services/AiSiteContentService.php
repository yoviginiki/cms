<?php

namespace App\Domain\Sites\Services;

use App\Services\AI\AnthropicClient;
use Illuminate\Support\Facades\Log;

/**
 * Generates industry-specific starter copy for the "Full Site" template
 * (headings + body for all 8 pages) from a business type the user names —
 * "HVAC company", "day spa", "boutique hotel", etc. Reuses the Anthropic client
 * with schema-forced JSON. Images are curated per-industry keyword sets served
 * by loremflickr (no API key); the AI also returns keywords so any topic beyond
 * the curated list still gets relevant photos.
 *
 * Degrades gracefully: generate() returns null on any failure and the template
 * falls back to its generic placeholder copy.
 */
class AiSiteContentService
{
    /** Curated industry → image search keywords (loremflickr tags). */
    private const IMAGE_KEYWORDS = [
        'hvac' => 'hvac,air,conditioning', 'plumb' => 'plumbing,pipes', 'massage' => 'massage,spa',
        'spa' => 'spa,wellness', 'hotel' => 'hotel,lobby', 'motel' => 'hotel,room',
        'restaurant' => 'restaurant,food', 'cafe' => 'cafe,coffee', 'bakery' => 'bakery,bread',
        'salon' => 'salon,hair', 'barber' => 'barber,haircut', 'gym' => 'gym,fitness',
        'yoga' => 'yoga,studio', 'dental' => 'dentist,clinic', 'clinic' => 'clinic,medical',
        'landscap' => 'landscaping,garden', 'lawn' => 'lawn,garden', 'clean' => 'cleaning,service',
        'roof' => 'roofing,house', 'electric' => 'electrician,wiring', 'real estate' => 'realestate,house',
        'law' => 'law,office', 'account' => 'accounting,office', 'photograph' => 'photography,camera',
        'auto' => 'car,repair', 'pet' => 'pet,dog', 'florist' => 'flowers,florist',
    ];

    public function __construct(private AnthropicClient $client) {}

    public function available(): bool
    {
        return $this->client->isConfigured();
    }

    /** @return array<string,mixed>|null structured content, or null on failure */
    public function generate(string $topic): ?array
    {
        $topic = trim(mb_substr($topic, 0, 120));
        if (!$this->available() || $topic === '') {
            return null;
        }

        try {
            $out = $this->client->complete(
                (string) (config('cms.theme_wizard.chat_model') ?? 'claude-sonnet-5'),
                $this->systemBlocks(),
                [['role' => 'user', 'content' => "Write website copy for this business: {$topic}"]],
                3000,
                $this->schema(),
            );
            $data = json_decode($out['text'] ?? '', true);
        } catch (\Throwable $e) {
            Log::warning('AiSiteContent: generation failed', ['topic' => $topic, 'err' => mb_substr($e->getMessage(), 0, 200)]);
            return null;
        }

        if (!is_array($data)) {
            return null;
        }
        $data['_images'] = $this->imageKeywords($topic, $data);
        return $data;
    }

    /** Build a stable, topic-relevant image URL for a given slot. */
    public function imageUrl(string $keywords, int $lock): string
    {
        return "https://loremflickr.com/1200/800/{$keywords}?lock={$lock}";
    }

    private function imageKeywords(string $topic, array $data): string
    {
        $t = mb_strtolower($topic);
        foreach (self::IMAGE_KEYWORDS as $needle => $kw) {
            if (str_contains($t, $needle)) {
                return $kw;
            }
        }
        // fall back to the AI's own suggestion, then a safe generic
        $ai = mb_strtolower(trim((string) ($data['image_keywords'] ?? '')));
        $ai = preg_replace('/[^a-z0-9, ]/', '', $ai);
        $ai = implode(',', array_slice(array_filter(preg_split('/[,\s]+/', (string) $ai)), 0, 3));
        return $ai !== '' ? $ai : 'business,office';
    }

    private function systemBlocks(): array
    {
        return [[
            'type' => 'text',
            'cache_control' => ['type' => 'ephemeral'],
            'text' => <<<'PROMPT'
You write concise, professional marketing copy for small-business websites. Given a business type, produce ready-to-publish copy for a standard 8-page site, tailored to that industry's customers and services.

Rules:
- Be specific to the industry (name real services, offerings, and value props a customer of THAT business would expect) — not generic filler.
- Headings are short and punchy; body copy is 1–2 sentences, warm and plain-spoken.
- No placeholders, no lorem ipsum, no markdown, no emojis. Plain sentences only.
- Provide EXACTLY: 3 landing highlights, 3 catalog items, 6 features, and 3 blog posts. Each blog post gets a 2–4 paragraph body (real, useful article content for that industry).
- image_keywords: 1–3 lowercase comma-separated photo tags for this industry (e.g. "hvac,air,conditioning").
Return ONLY the JSON object matching the schema.
PROMPT,
        ]];
    }

    /** JSON schema forcing the full-site content shape. */
    private function schema(): array
    {
        $str = fn (int $max) => ['type' => 'string', 'maxLength' => $max];
        $titleDesc = [
            'type' => 'object',
            'properties' => ['title' => $str(60), 'desc' => $str(200)],
            'required' => ['title', 'desc'], 'additionalProperties' => false,
        ];
        $items = fn (int $n) => ['type' => 'array', 'items' => $titleDesc];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['image_keywords', 'home', 'landing', 'catalog', 'portfolio', 'contact', 'about', 'features', 'blog'],
            'properties' => [
                'image_keywords' => $str(60),
                'home' => $this->obj(['heading' => $str(80), 'subtext' => $str(240), 'cta' => $str(30)]),
                'landing' => $this->obj([
                    'heading' => $str(80), 'subtext' => $str(240), 'cta' => $str(30),
                    'closing_heading' => $str(80), 'features' => $items(3),
                ]),
                'catalog' => $this->obj([
                    'heading' => $str(60), 'intro' => $str(200),
                    'items' => ['type' => 'array', 'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['title', 'subtitle', 'desc'],
                        'properties' => ['title' => $str(60), 'subtitle' => $str(40), 'desc' => $str(200)],
                    ]],
                ]),
                'portfolio' => $this->obj(['heading' => $str(60), 'intro' => $str(200)]),
                'contact' => $this->obj(['heading' => $str(60), 'intro' => $str(240)]),
                'about' => $this->obj(['heading' => $str(60), 'paragraph1' => $str(320), 'paragraph2' => $str(320)]),
                'features' => $this->obj(['heading' => $str(60), 'intro' => $str(200), 'items' => $items(6)]),
                'blog' => $this->obj([
                    'heading' => $str(60),
                    'posts' => ['type' => 'array', 'items' => [
                        'type' => 'object', 'additionalProperties' => false,
                        'required' => ['title', 'excerpt', 'body'],
                        'properties' => [
                            'title' => $str(80), 'excerpt' => $str(300),
                            // 2–4 short body paragraphs (plain sentences)
                            'body' => ['type' => 'array', 'items' => $str(600)],
                        ],
                    ]],
                ]),
            ],
        ];
    }

    /** @param array<string,mixed> $props */
    private function obj(array $props): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($props),
            'properties' => $props,
        ];
    }
}
