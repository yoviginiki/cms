<?php

namespace App\Domain\Collections\Queries;

use App\Models\SavedQuery;
use Illuminate\Validation\ValidationException;

/**
 * Track G-Q — the ONE execution entry for saved queries (blocks, publish,
 * public endpoints, previews). Resolves declared public params (typed,
 * undeclared rejected), dispatches by mode, returns the result-shape
 * contract. SQL mode lands in G-Q2 behind the same interface.
 */
class QueryRunner
{
    public function __construct(private SimpleQueryCompiler $compiler)
    {
    }

    /**
     * @param array<string, mixed> $requestParams raw caller-supplied params
     */
    public function run(SavedQuery $query, array $requestParams = []): array
    {
        $params = $this->resolveParams($query, $requestParams);

        return match ($query->mode) {
            'simple' => $this->runSimple($query, $params),
            default => throw ValidationException::withMessages(['mode' => 'SQL-mode execution arrives with G-Q2.']),
        };
    }

    private function runSimple(SavedQuery $query, array $params): array
    {
        $collection = $query->sourceCollection();
        if (!$collection) {
            throw ValidationException::withMessages(['collection_id' => 'The source collection no longer exists.']);
        }

        return $this->compiler->run($collection, $query->definition, $params);
    }

    /**
     * Declared params are the ONLY accepted request parameters — anything
     * undeclared is rejected outright; values are coerced to declared types.
     *
     * @return array<string, mixed>
     */
    public function resolveParams(SavedQuery $query, array $requestParams): array
    {
        $declared = collect($query->public_params ?? [])->keyBy('key');

        $unknown = array_diff(array_keys($requestParams), $declared->keys()->all());
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'params' => 'Unknown parameter(s): ' . implode(', ', array_map('strval', $unknown)) . '.',
            ]);
        }

        $resolved = [];
        foreach ($declared as $key => $param) {
            $raw = $requestParams[$key] ?? $param['default'] ?? null;
            if ($raw === null) {
                if ($param['required'] ?? false) {
                    throw ValidationException::withMessages([$key => "Parameter '{$key}' is required."]);
                }
                continue;
            }
            $resolved[$key] = match ($param['type'] ?? 'text') {
                'number' => is_numeric($raw) ? $raw + 0 : throw ValidationException::withMessages([$key => "'{$key}' must be a number."]),
                'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                default => mb_substr((string) $raw, 0, 200),
            };
        }

        return $resolved;
    }
}
