<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Interactive meditation timer — a progress ring with preset durations, a soft
 * bell, and optional multi-day "journeys" whose completion is stored in the
 * visitor's localStorage. Behaviour comes from the self-hosted app-tools
 * runtime (AppToolRender).
 */
class MeditationTimerBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'meditation-timer'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'eyebrow'        => ['sometimes', 'nullable', 'string', 'max:80'],
            'title'          => ['sometimes', 'nullable', 'string', 'max:120'],
            'presets'        => ['sometimes', 'array', 'max:12'],
            'presets.*'      => ['integer', 'min:1', 'max:180'],
            'defaultMinutes' => ['sometimes', 'integer', 'min:1', 'max:180'],
            'storeKey'       => ['sometimes', 'nullable', 'string', 'max:60'],
            'showJourneys'   => ['sometimes', 'boolean'],
            'journeys'       => ['sometimes', 'array', 'max:12'],
        ];
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
