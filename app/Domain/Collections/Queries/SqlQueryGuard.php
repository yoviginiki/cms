<?php

namespace App\Domain\Collections\Queries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Track G-Q2 — parse-time defense for Advanced SQL mode. This is deliberately
 * a STRICT allow-list validator, not a full SQL parser: single SELECT (or
 * EXPLAIN SELECT) statement, no DML/DDL/DCL keywords anywhere, no system
 * catalogs, no quoting tricks, whitelisted functions only, and every
 * relation reference must be one of the site's scoped views. The restricted
 * Postgres role + security_invoker views + RLS are the runtime walls behind
 * it — this layer exists to fail fast with friendly messages and to keep
 * probing noise away from the database.
 *
 * Strictness over convenience: a field key that collides with a blocked
 * keyword (e.g. a column literally named "update") is rejected — documented
 * as a known wall.
 */
class SqlQueryGuard
{
    private const MAX_LENGTH = 10000;

    /** Rejected ANYWHERE in the statement (word-boundary, case-insensitive). */
    private const FORBIDDEN = [
        'insert', 'update', 'delete', 'merge', 'drop', 'alter', 'create', 'grant', 'revoke',
        'truncate', 'copy', 'vacuum', 'analyze', 'call', 'do', 'set', 'reset', 'show',
        'listen', 'notify', 'prepare', 'execute', 'deallocate', 'declare', 'fetch', 'move',
        'lock', 'cluster', 'reindex', 'comment', 'security', 'into', 'returning', 'refresh',
        'import', 'load', 'checkpoint', 'discard', 'abort', 'begin', 'commit', 'rollback',
        'savepoint', 'release', 'share', 'nowait', 'skip',
    ];

    /** Structural SQL words that may legally be followed by '(' or appear anywhere. */
    private const KEYWORDS = [
        'select', 'from', 'where', 'join', 'left', 'right', 'inner', 'outer', 'full', 'cross',
        'on', 'as', 'and', 'or', 'not', 'in', 'exists', 'any', 'all', 'some', 'between',
        'like', 'ilike', 'similar', 'is', 'null', 'true', 'false', 'unknown', 'case', 'when',
        'then', 'else', 'end', 'group', 'by', 'order', 'having', 'limit', 'offset', 'distinct',
        'union', 'except', 'intersect', 'asc', 'desc', 'nulls', 'first', 'last', 'with',
        'recursive', 'using', 'natural', 'lateral', 'filter', 'over', 'partition', 'range',
        'rows', 'unbounded', 'preceding', 'following', 'current', 'row', 'interval', 'escape',
        'explain', 'values', 'to',
    ];

    private const FUNCTIONS = [
        'count', 'sum', 'avg', 'min', 'max', 'round', 'abs', 'ceil', 'ceiling', 'floor',
        'coalesce', 'nullif', 'greatest', 'least', 'lower', 'upper', 'initcap', 'length',
        'char_length', 'substring', 'substr', 'concat', 'concat_ws', 'trim', 'ltrim', 'rtrim',
        'replace', 'split_part', 'position', 'strpos', 'lpad', 'rpad', 'reverse', 'repeat',
        'to_char', 'to_number', 'to_date', 'to_timestamp', 'date_trunc', 'date_part',
        'extract', 'age', 'now', 'current_date', 'current_timestamp', 'make_date',
        'string_agg', 'array_agg', 'unnest', 'array_length', 'cardinality',
        'jsonb_array_elements_text', 'jsonb_array_length', 'jsonb_typeof',
        'row_number', 'rank', 'dense_rank', 'ntile', 'lag', 'lead', 'first_value', 'last_value',
        'cast', 'mod', 'power', 'sqrt', 'exp', 'ln', 'log', 'sign', 'trunc', 'width_bucket',
        'left', 'right', // string functions; also join keywords — allowed either way
    ];

    private const ALLOWED_CAST_TYPES = [
        'int', 'integer', 'bigint', 'smallint', 'numeric', 'decimal', 'real', 'float',
        'double', 'precision', 'text', 'varchar', 'char', 'date', 'timestamp', 'timestamptz',
        'time', 'boolean', 'bool', 'interval', 'jsonb', 'json',
    ];

    /**
     * @param array<int, string> $allowedViews the site's scoped view names
     * @throws SqlGuardException
     */
    public function validate(string $sql, array $allowedViews): void
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new SqlGuardException('Write a SELECT statement.');
        }
        if (mb_strlen($sql) > self::MAX_LENGTH) {
            throw new SqlGuardException('Query too long (max ' . self::MAX_LENGTH . ' characters).');
        }

        // Quoting tricks rejected outright before stripping.
        if (str_contains($sql, '"')) {
            throw new SqlGuardException('Double-quoted identifiers aren\'t allowed — view and column names are plain lowercase.');
        }
        if (str_contains($sql, '$')) {
            throw new SqlGuardException('Dollar quoting isn\'t allowed.');
        }
        if (preg_match('/\b[eE]\'/', $sql)) {
            throw new SqlGuardException('Escape-string literals (E\'…\') aren\'t allowed.');
        }

        $stripped = $this->stripLiteralsAndComments($sql);

        // Single statement: at most one trailing semicolon.
        $inner = rtrim($stripped);
        $inner = rtrim($inner, ';');
        if (str_contains($inner, ';')) {
            throw new SqlGuardException('One statement only — no semicolon chaining.');
        }

        $tokens = $this->tokenize($inner);
        if ($tokens === []) {
            throw new SqlGuardException('Write a SELECT statement.');
        }

        // First token: SELECT, WITH or EXPLAIN.
        $first = strtolower($tokens[0]['t']);
        if (!in_array($first, ['select', 'with', 'explain'], true)) {
            throw new SqlGuardException('Only SELECT statements run here (EXPLAIN is allowed for learning).');
        }

        $wordTokens = array_values(array_filter($tokens, fn ($tok) => $tok['kind'] === 'word'));
        $lowerWords = array_map(fn ($tok) => strtolower($tok['t']), $wordTokens);

        // Forbidden keywords anywhere.
        foreach ($lowerWords as $word) {
            if (in_array($word, self::FORBIDDEN, true)) {
                throw new SqlGuardException("'" . strtoupper($word) . "' isn't allowed — this editor is read-only SELECT.");
            }
            if (str_starts_with($word, 'pg_') || $word === 'information_schema' || str_starts_with($word, 'cq_')) {
                throw new SqlGuardException('System catalogs and other schemas are off limits — query your own col_/rel_ views.');
            }
        }

        // Blocked base tables (the real schema is invisible from this editor).
        $realTables = $this->realTableNames();
        foreach ($lowerWords as $word) {
            if (in_array($word, $realTables, true)) {
                throw new SqlGuardException("'{$word}' isn't queryable here — use your collection views (col_…, rel_…).");
            }
        }

        // Function whitelist: word directly followed by '('.
        foreach ($tokens as $i => $token) {
            if ($token['kind'] !== 'word') {
                continue;
            }
            $next = $tokens[$i + 1] ?? null;
            if ($next && $next['t'] === '(') {
                $word = strtolower($token['t']);
                if (!in_array($word, self::FUNCTIONS, true) && !in_array($word, self::KEYWORDS, true)) {
                    throw new SqlGuardException("Function '{$word}()' isn't in the allowed list.");
                }
            }
        }

        // Cast types after '::'.
        foreach ($tokens as $i => $token) {
            if ($token['t'] === '::') {
                $next = strtolower($tokens[$i + 1]['t'] ?? '');
                if (!in_array($next, self::ALLOWED_CAST_TYPES, true)) {
                    throw new SqlGuardException("Cast to '{$next}' isn't allowed.");
                }
            }
        }

        // Relation positions: token after FROM/JOIN must be '(' or an owned view.
        $sawView = false;
        foreach ($tokens as $i => $token) {
            if ($token['kind'] !== 'word') {
                continue;
            }
            $word = strtolower($token['t']);
            if ($word === 'from' || $word === 'join') {
                $next = $tokens[$i + 1] ?? null;
                if (!$next) {
                    throw new SqlGuardException('Dangling FROM/JOIN.');
                }
                if ($next['t'] === '(') {
                    continue; // subquery
                }
                $rel = strtolower($next['t']);
                // Only enforce on tokens shaped like a collection view. Real
                // tables/catalogs are already rejected above; CTE names,
                // subquery aliases, and inner-FROM function syntax
                // (extract(year FROM col), substring(x FROM 1)) are left alone
                // — an unknown relation there just fails at runtime under the
                // restricted role with a friendly "relation does not exist".
                if (!preg_match('/^(col|rel)_[a-z0-9_]+$/', $rel)) {
                    continue;
                }
                if (!in_array($rel, $allowedViews, true)) {
                    throw new SqlGuardException("View '{$rel}' doesn't exist on this site.");
                }
                $sawView = true;
            }
        }

        // EXPLAIN: bare EXPLAIN only (ANALYZE executes the query twice).
        if ($first === 'explain' && in_array('analyze', $lowerWords, true)) {
            throw new SqlGuardException('EXPLAIN ANALYZE isn\'t allowed — plain EXPLAIN shows the plan.');
        }

        if (!$sawView && $first !== 'explain') {
            // Queries must read at least one owned view (SELECT 1 etc. is pointless here).
            $usesAnyView = array_intersect($lowerWords, $allowedViews) !== [];
            if (!$usesAnyView) {
                throw new SqlGuardException('Query at least one of your collection views (col_…, rel_…).');
            }
        }
    }

    /** Replace string literals and comments with spaces (positions preserved). */
    private function stripLiteralsAndComments(string $sql): string
    {
        $out = '';
        $len = strlen($sql);
        $i = 0;
        while ($i < $len) {
            $ch = $sql[$i];
            if ($ch === "'") {
                $out .= ' ';
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === "'" && ($sql[$i + 1] ?? '') === "'") {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === "'") {
                        $i++;
                        break;
                    }
                    $i++;
                }
                $out .= ' ';
                continue;
            }
            if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            if ($ch === '/' && ($sql[$i + 1] ?? '') === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) {
                    $i++;
                }
                $i += 2;
                $out .= ' ';
                continue;
            }
            $out .= $ch;
            $i++;
        }

        return $out;
    }

    /** @return array<int, array{t: string, kind: 'word'|'punct'}> */
    private function tokenize(string $sql): array
    {
        preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*|::|[0-9]+(?:\.[0-9]+)?|[^\sa-zA-Z0-9_]/', $sql, $matches);

        return array_map(
            fn ($t) => ['t' => $t, 'kind' => preg_match('/^[a-zA-Z_]/', $t) ? 'word' : 'punct'],
            $matches[0],
        );
    }

    /** @return array<int, string> real public-schema table names (cached) */
    private function realTableNames(): array
    {
        return Cache::remember('sql_guard_tables', 3600, function () {
            try {
                return array_map(
                    fn ($row) => strtolower($row->tablename),
                    DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"),
                );
            } catch (\Throwable) {
                return ['records', 'collections', 'record_relations', 'sites', 'users', 'tenants', 'pages', 'posts', 'assets', 'saved_queries', 'deployments', 'blocks'];
            }
        });
    }
}
