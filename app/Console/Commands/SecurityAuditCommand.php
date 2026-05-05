<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SecurityAuditCommand extends Command
{
    protected $signature = 'security:audit';
    protected $description = 'Run security audit checks';

    public function handle(): int
    {
        $this->info('Running security audit...');
        $issues = 0;

        // 1. Check DB user is not superuser
        $result = DB::select("SELECT usesuper FROM pg_user WHERE usename = current_user");
        if (!empty($result) && $result[0]->usesuper) {
            $this->error('FAIL: Database user is a superuser — RLS will be bypassed!');
            $issues++;
        } else {
            $this->info('PASS: Database user is not a superuser');
        }

        // 2. Check RLS is enabled on critical tables
        $tables = ['sites', 'pages', 'posts', 'blocks', 'categories', 'assets', 'deployments', 'themes', 'block_templates', 'page_versions', 'deploy_artifacts'];
        foreach ($tables as $table) {
            $rls = DB::select("SELECT rowsecurity FROM pg_tables WHERE tablename = ? AND schemaname = 'public'", [$table]);
            if (empty($rls) || !$rls[0]->rowsecurity) {
                $this->error("FAIL: RLS not enabled on table '{$table}'");
                $issues++;
            }
        }
        if ($issues === 0) {
            $this->info('PASS: RLS enabled on all ' . count($tables) . ' tables');
        }

        // 3. Check session config
        $checks = [
            ['SESSION_SECURE_COOKIE', config('session.secure'), true],
            ['SESSION_HTTP_ONLY', config('session.http_only'), true],
            ['SESSION_SAME_SITE', config('session.same_site'), 'lax'],
        ];
        foreach ($checks as [$name, $actual, $expected]) {
            if ($actual != $expected) {
                $this->error("FAIL: {$name} should be '{$expected}', got '{$actual}'");
                $issues++;
            } else {
                $this->info("PASS: {$name} = {$actual}");
            }
        }

        // 4. Check APP_DEBUG
        if (config('app.debug') && config('app.env') === 'production') {
            $this->warn('WARN: APP_DEBUG is true in production');
            $issues++;
        } else {
            $this->info('PASS: APP_DEBUG is appropriate for environment');
        }

        // 5. Check storage permissions
        $storagePath = storage_path();
        if (is_writable($storagePath)) {
            $this->info('PASS: Storage directory is writable');
        } else {
            $this->error('FAIL: Storage directory is not writable');
            $issues++;
        }

        // 6. Check .env not accessible via web
        $publicEnv = public_path('.env');
        if (file_exists($publicEnv)) {
            $this->error('FAIL: .env file found in public directory!');
            $issues++;
        } else {
            $this->info('PASS: .env not in public directory');
        }

        $this->newLine();
        if ($issues > 0) {
            $this->error("Audit completed with {$issues} issue(s)");
            return Command::FAILURE;
        }

        $this->info('Audit completed — all checks passed!');
        return Command::SUCCESS;
    }
}
