<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Interactive breathing pacer — an animated orb with per-phase duration
 * controls, round selection and optional audio cues. Behaviour is provided by
 * the self-hosted app-tools runtime (AppToolRender); this block renders the
 * markup + a JSON config the runtime reads.
 */
class BreathingPacerBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'breathing-pacer'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'eyebrow'       => ['sometimes', 'nullable', 'string', 'max:80'],
            'title'         => ['sometimes', 'nullable', 'string', 'max:120'],
            'soundLabel'    => ['sometimes', 'nullable', 'string', 'max:60'],
            'soundDefault'  => ['sometimes', 'boolean'],
            'advancedAt'    => ['sometimes', 'integer', 'min:0', 'max:600'],
            'defaultRounds' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'roundOptions'  => ['sometimes', 'array', 'max:12'],
            'roundOptions.*'=> ['integer', 'min:1', 'max:99'],
            'phases'        => ['sometimes', 'array', 'max:12'],
            'phases.*.label'=> ['sometimes', 'string', 'max:60'],
            'phases.*.value'=> ['sometimes', 'numeric', 'min:0', 'max:600'],
            'phases.*.min'  => ['sometimes', 'numeric', 'min:0', 'max:600'],
            'phases.*.max'  => ['sometimes', 'numeric', 'min:0', 'max:600'],
            'phases.*.step' => ['sometimes', 'numeric', 'min:0.1', 'max:60'],
            'phases.*.locked' => ['sometimes', 'boolean'],
        ];
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
