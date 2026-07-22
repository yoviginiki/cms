<?php

namespace App\Domain\Blocks\Definitions;

/**
 * Interactive guided-coordination trainer — a phase visual that walks through a
 * configurable sequence of cued steps for a number of rounds. Behaviour comes
 * from the self-hosted app-tools runtime (AppToolRender).
 */
class PelvicTrainerBlockDefinition implements BlockDefinition
{
    public function type(): string { return 'pelvic-trainer'; }
    public function category(): string { return 'interactive'; }

    public function validationRules(): array
    {
        return [
            'eyebrow'          => ['sometimes', 'nullable', 'string', 'max:80'],
            'rounds'           => ['sometimes', 'integer', 'min:1', 'max:60'],
            'phases'           => ['sometimes', 'array', 'max:12'],
            'phases.*.label'   => ['sometimes', 'string', 'max:60'],
            'phases.*.cue'     => ['sometimes', 'string', 'max:240'],
            'phases.*.seconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ];
    }

    public function sanitizationConfig(): array { return ['HTML.Allowed' => '']; }
    public function allowsChildren(): bool { return false; }
    public function maxChildren(): ?int { return null; }
}
