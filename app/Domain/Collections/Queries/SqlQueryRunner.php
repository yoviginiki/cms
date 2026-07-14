<?php

namespace App\Domain\Collections\Queries;

use App\Models\Site;
use Illuminate\Support\Facades\DB;

/**
 * Track G-Q2 — executes guarded SELECT SQL under the restricted role. The
 * flow, all inside one transaction that is ALWAYS rolled back:
 *   SET LOCAL ROLE cms_sql_guest          (loses every app-role privilege)
 *   SET LOCAL search_path = <site schema> (only col_/rel_ views resolve)
 *   SET LOCAL statement_timeout           (~3s wall)
 *   EXPLAIN cost guard                    (reject pathological plans early)
 *   run the wrapped query with an auto-LIMIT row cap
 *
 * The guard (SqlQueryGuard) is the parse-time wall; this is the runtime wall.
 * Both must pass. Never trust one alone.
 */
class SqlQueryRunner
{
    public function __construct(
        private SqlQueryGuard $guard,
        private ScopedViewManager $views,
    ) {
    }

    public function roleAvailable(): bool
    {
        return $this->views->guestRoleExists();
    }

    /**
     * Validate + execute. Returns the result-shape contract:
     *   {type:'table', columns:[{key,label}], rows:[{...}], total, capped}
     *   {type:'plan', text}  for EXPLAIN.
     *
     * @throws SqlGuardException on any guard/runtime violation
     */
    public function run(Site $site, string $sql): array
    {
        $this->guard->validate($sql, $this->views->viewNames($site));

        if (!$this->roleAvailable()) {
            throw new SqlGuardException('SQL mode is not provisioned on this server (missing restricted role).');
        }

        return $this->execute($site, $sql, guarded: true);
    }

    /**
     * TEST-ONLY: run raw SQL under the restricted role WITHOUT the parse guard,
     * to prove the role/RLS wall holds even on a guard bypass. Returns raw rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function runUnguardedForTests(Site $site, string $sql): array
    {
        $result = $this->execute($site, $sql, guarded: false, wrap: false);

        return $result['rows'] ?? [];
    }

    private function execute(Site $site, string $sql, bool $guarded, bool $wrap = true): array
    {
        $schema = $this->views->schemaName($site);
        $timeoutMs = (int) config('collections.sql_timeout_ms', 3000);
        $rowCap = (int) config('collections.sql_row_cap', 500);
        $costLimit = (float) config('collections.sql_cost_limit', 5_000_000);

        $isExplain = (bool) preg_match('/^\s*explain\b/i', $sql);

        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $connection->statement('SET LOCAL ROLE cms_sql_guest');
            $connection->statement("SET LOCAL search_path = {$schema}");
            $connection->statement("SET LOCAL statement_timeout = {$timeoutMs}");
            $connection->statement('SET LOCAL lock_timeout = 1000');

            if ($isExplain) {
                $rows = $connection->select($sql);
                $text = implode("\n", array_map(fn ($r) => ((array) $r)['QUERY PLAN'] ?? '', $rows));

                return ['type' => 'plan', 'text' => $text];
            }

            // Cost guard: EXPLAIN the (wrapped) query and reject pathological plans.
            if ($guarded) {
                $plan = $connection->select('EXPLAIN (FORMAT JSON) ' . $sql);
                $planJson = json_decode(((array) $plan[0])['QUERY PLAN'] ?? '[]', true);
                $totalCost = $planJson[0]['Plan']['Total Cost'] ?? 0;
                if ($totalCost > $costLimit) {
                    $connection->rollBack();
                    throw new SqlGuardException('This query is too expensive to run — add filters or a smaller LIMIT.');
                }
            }

            $runSql = $wrap
                ? "SELECT * FROM (\n{$sql}\n) AS _q LIMIT " . ($rowCap + 1)
                : $sql;

            $rows = array_map(fn ($r) => (array) $r, $connection->select($runSql));

            $connection->rollBack();

            $capped = count($rows) > $rowCap;
            if ($capped) {
                $rows = array_slice($rows, 0, $rowCap);
            }

            $columns = $rows === []
                ? []
                : array_map(fn ($k) => ['key' => $k, 'label' => $this->humanize($k)], array_keys($rows[0]));

            return [
                'type' => 'table',
                'columns' => $columns,
                'rows' => $rows,
                'total' => count($rows),
                'capped' => $capped,
            ];
        } catch (SqlGuardException $e) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $message = $e->getMessage();
            if (str_contains($message, 'statement timeout') || str_contains($message, 'canceling statement')) {
                throw new SqlGuardException('The query took too long and was cancelled — narrow it with filters or a LIMIT.');
            }
            if (str_contains($message, 'permission denied')) {
                throw new SqlGuardException('That table or column isn\'t available to queries — use your col_/rel_ views.');
            }
            throw new SqlGuardException('Query error: ' . $this->cleanError($message));
        }
    }

    private function humanize(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    private function cleanError(string $message): string
    {
        // Trim the driver prefix noise ("SQLSTATE[...]: ...:") to the human part.
        if (preg_match('/ERROR:\s*(.+?)(?:\s*\(SQL:|$)/s', $message, $m)) {
            return trim($m[1]);
        }

        return mb_substr($message, 0, 200);
    }
}
