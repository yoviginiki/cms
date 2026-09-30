<?php

namespace App\Domain\Blocks\Definitions;

class SpotifyBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'spotify'; }
    public function category(): string { return 'media'; }

    public function validationRules(): array
    {
        return [
            // share link, spotify: URI or the pasted <iframe> embed code —
            // parsed by SpotifyEmbed at render time, never output raw
            'url'    => ['sometimes', 'nullable', 'string', 'max:4096'],
            'theme'  => ['sometimes', 'in:color,dark'],
            'size'   => ['sometimes', 'in:normal,compact'],
            'height' => ['sometimes', 'nullable', 'integer', 'min:80', 'max:1200'],
        ];
    }

    public function sanitizationConfig(): array
    {
        return ['HTML.Allowed' => ''];
    }

    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
