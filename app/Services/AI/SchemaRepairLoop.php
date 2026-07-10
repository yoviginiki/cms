<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Generic "structured output with repair" loop, extracted from
 * FlatplanEngine::generateWithRepair (T3 W0). Runs an AI completion, decodes
 * its JSON, runs a caller-supplied semantic validator, and on failure feeds
 * the validation errors back into the conversation and retries once (or N).
 *
 * The schema is enforced server-side by the model (json_schema output), so
 * malformed JSON is rare; this loop catches the semantic-rule failures the
 * schema can't express (e.g. "accent must differ from background").
 *
 * Domain-agnostic: the caller binds model/system/schema inside `$complete` and
 * supplies `$validate`. Returns the validated decoded data plus every usage
 * record accumulated across attempts (so budgets are charged for retries too).
 */
class SchemaRepairLoop
{
    /**
     * @param callable(array $messages): array{text: string, usage: array} $complete
     *        Runs one completion for the given messages; returns {text, usage}.
     * @param callable(mixed $decoded): array<int,string> $validate
     *        Semantic validator: returns human-readable errors ([] = valid).
     * @param array<int,array{role:string,content:mixed}> $messages Initial conversation.
     * @param int $maxAttempts Total attempts (default 2 = one repair).
     * @return array{data: mixed, usages: array<int,array>}
     */
    public function run(callable $complete, callable $validate, array $messages, int $maxAttempts = 2): array
    {
        $usages = [];
        $lastErrors = ['No attempts ran.'];

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $result = $complete($messages);
            $usages[] = $result['usage'] ?? [];

            $decoded = json_decode($result['text'] ?? '', true);
            if (!is_array($decoded)) {
                $lastErrors = ['Output was not valid JSON.'];
            } else {
                $errors = $validate($decoded);
                if ($errors === []) {
                    return ['data' => $decoded, 'usages' => $usages];
                }
                $lastErrors = $errors;
            }

            // feed the failure back for the next attempt
            $messages[] = ['role' => 'assistant', 'content' => $result['text'] ?? ''];
            $messages[] = [
                'role' => 'user',
                'content' => "Your previous response failed validation:\n- "
                    . implode("\n- ", array_slice($lastErrors, 0, 20))
                    . "\n\nReturn the corrected JSON only — no prose.",
            ];
        }

        throw new RuntimeException(
            'AI output failed validation after ' . $maxAttempts . ' attempts: '
            . implode('; ', array_slice($lastErrors, 0, 5))
        );
    }
}
