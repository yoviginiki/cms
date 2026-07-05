<?php

namespace App\Services\IssueStudio;

/**
 * Hard validation of the flatplan JSON contract (see flatplan.md).
 * Returns a flat list of human-readable errors — empty means valid.
 * These errors are also what the repair round-trip feeds back to Opus.
 */
class FlatplanValidator
{
    public function __construct(
        private Playbook $playbook,
    ) {
    }

    /**
     * @param array $spreads decoded spreads array
     * @param array $brief   the session brief (for material id resolution)
     * @return string[] errors
     */
    public function validate(array $spreads, array $brief): array
    {
        $errors = [];

        if (count($spreads) < 2) {
            return ['A flatplan needs at least a cover and a closing spread.'];
        }

        $patterns = $this->playbook->patternNames();
        $materialIds = array_map(fn ($m) => $m['id'] ?? '', $brief['materials'] ?? []);

        // positions must be exactly 0..n-1 in order
        foreach (array_values($spreads) as $i => $spread) {
            $pos = $spread['position'] ?? null;
            if ($pos !== $i) {
                $errors[] = "Spread at index {$i} has position " . var_export($pos, true) . ", expected {$i} (positions must be 0..n contiguous, in order).";
            }
        }

        foreach ($spreads as $spread) {
            $pos = (int) ($spread['position'] ?? -1);
            $label = "Spread {$pos}";

            if (trim((string) ($spread['working_title'] ?? '')) === '') {
                $errors[] = "{$label}: working_title is empty.";
            }
            if (trim((string) ($spread['intent'] ?? '')) === '') {
                $errors[] = "{$label}: intent is empty.";
            }
            if (!in_array($spread['section'] ?? '', ['cover', 'fob', 'feature', 'bob'], true)) {
                $errors[] = "{$label}: section must be cover|fob|feature|bob.";
            }

            $pattern = (string) ($spread['pattern'] ?? '');
            $isCoverPattern = in_array($pattern, $patterns['covers'], true);
            $isSpreadPattern = in_array($pattern, $patterns['spreads'], true);

            if (!$isCoverPattern && !$isSpreadPattern) {
                $errors[] = "{$label}: unknown pattern \"{$pattern}\" — use only names from spread-patterns.md.";
            } elseif ($pos === 0 && !$isCoverPattern) {
                $errors[] = "Position 0 must use a cover treatment (" . implode(', ', $patterns['covers']) . ').';
            } elseif ($pos !== 0 && $isCoverPattern) {
                $errors[] = "{$label}: cover treatments are only allowed at position 0.";
            }

            if ($pos === 0 && ($spread['section'] ?? '') !== 'cover') {
                $errors[] = 'Position 0 must have section "cover".';
            }

            foreach ($spread['materials'] ?? [] as $mid) {
                if (!in_array($mid, $materialIds, true)) {
                    $errors[] = "{$label}: material \"{$mid}\" does not exist in the brief inventory.";
                }
            }
        }

        $last = end($spreads);
        if (($last['pattern'] ?? '') !== 'closer-colophon') {
            $errors[] = 'The last spread must use the closer-colophon pattern (every issue ends deliberately).';
        }

        return $errors;
    }

    /** Validate one revised spread in the context of the existing flatplan. */
    public function validateSingle(array $spread, array $allSpreads, array $brief): array
    {
        $merged = $allSpreads;
        foreach ($merged as $i => $existing) {
            if (($existing['position'] ?? null) === ($spread['position'] ?? null)) {
                $merged[$i] = $spread;
            }
        }

        return $this->validate($merged, $brief);
    }
}
