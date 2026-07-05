<?php

namespace App\Services\IssueStudio;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Per-tenant monthly token budget, backed by the (previously unused)
 * tenants.monthly_token_budget / monthly_tokens_used / token_usage_reset_at
 * columns. Budget <= 0 means unlimited.
 */
class TokenBudget
{
    public function assertAvailable(string $tenantId): void
    {
        $tenant = $this->freshTenant($tenantId);
        if (!$tenant) {
            return;
        }

        $budget = (int) ($tenant->monthly_token_budget ?? 0);
        if ($budget <= 0) {
            return;
        }

        if ((int) $tenant->monthly_tokens_used >= $budget) {
            throw new RuntimeException(
                'Monthly AI token budget exhausted (' . number_format($budget) . ' tokens). Resets next month.'
            );
        }
    }

    /** Record spend (input + output tokens count against the budget). */
    public function record(string $tenantId, array $usage): void
    {
        $tokens = (int) ($usage['input'] ?? 0) + (int) ($usage['output'] ?? 0);
        if ($tokens <= 0) {
            return;
        }

        DB::table('tenants')->where('id', $tenantId)->increment('monthly_tokens_used', $tokens);
    }

    /** Reset the counter when a new calendar month has started. */
    private function freshTenant(string $tenantId): ?object
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            return null;
        }

        $resetAt = $tenant->token_usage_reset_at ? \Carbon\Carbon::parse($tenant->token_usage_reset_at) : null;
        if (!$resetAt || $resetAt->isPast()) {
            DB::table('tenants')->where('id', $tenantId)->update([
                'monthly_tokens_used' => 0,
                'token_usage_reset_at' => now()->startOfMonth()->addMonth(),
            ]);
            $tenant->monthly_tokens_used = 0;
        }

        return $tenant;
    }
}
