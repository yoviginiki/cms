<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Services\Theme\Coverage\ThemeCoverageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ThemeCoverageCommand extends Command
{
    protected $signature = 'theme:coverage {theme_id?} {--mode=light} {--all-modes} {--format=table}';
    protected $description = 'Analyze theme coverage against all registered blocks';

    public function handle(ThemeCoverageService $service): int
    {
        $tenant = DB::selectOne('SELECT id FROM tenants LIMIT 1');
        if ($tenant) {
            DB::statement("SET app.current_tenant_id = '{$tenant->id}'");
        }

        $themeId = $this->argument('theme_id');
        $allModes = $this->option('all-modes');
        $format = $this->option('format');

        if ($themeId) {
            $themes = collect([Theme::find($themeId)])->filter();
        } else {
            DB::statement('ALTER TABLE themes DISABLE ROW LEVEL SECURITY');
            $themes = Theme::whereNotNull('document')->get();
            DB::statement('ALTER TABLE themes ENABLE ROW LEVEL SECURITY');
        }

        if ($themes->isEmpty()) {
            $this->warn('No themes found.');
            return 2;
        }

        $hasFailure = false;
        $hasWarning = false;

        foreach ($themes as $theme) {
            $modes = $allModes ? ($theme->modes ?? ['light']) : [$this->option('mode')];

            foreach ($modes as $mode) {
                $report = $service->analyze($theme->id, $mode);

                if ($format === 'json') {
                    $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT));
                } elseif ($format === 'junit') {
                    $this->outputJunit($theme, $report);
                } else {
                    $this->outputTable($theme, $mode, $report);
                }

                if (!$report->isPassing()) $hasFailure = true;
                if ($report->warningCount() > 0) $hasWarning = true;
            }
        }

        if ($hasFailure) return 1;
        if ($hasWarning) return 2;
        return 0;
    }

    private function outputTable(Theme $theme, string $mode, $report): void
    {
        $status = $report->isPassing() ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
        $this->line('');
        $this->line("  {$status} {$theme->name} ({$mode}) — {$report->criticalCount()} critical, {$report->warningCount()} warnings, {$report->fallbackCount()} fallbacks");

        if (!empty($report->gaps)) {
            $rows = [];
            foreach ($report->gaps as $gap) {
                $rows[] = [
                    $gap['severity']->value,
                    $gap['block'],
                    $gap['tokenPath'],
                    $gap['purpose'],
                ];
            }
            $this->table(['Severity', 'Block', 'Token', 'Purpose'], $rows);
        }
    }

    private function outputJunit(Theme $theme, $report): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<testsuites>' . PHP_EOL;
        $xml .= "  <testsuite name=\"{$theme->name} ({$report->mode})\" tests=\"1\">" . PHP_EOL;

        if ($report->isPassing()) {
            $xml .= '    <testcase name="coverage" />' . PHP_EOL;
        } else {
            $xml .= '    <testcase name="coverage">' . PHP_EOL;
            foreach ($report->gaps as $gap) {
                if ($gap['severity']->value === 'critical') {
                    $xml .= "      <failure message=\"{$gap['block']}: missing {$gap['tokenPath']}\" />" . PHP_EOL;
                }
            }
            $xml .= '    </testcase>' . PHP_EOL;
        }

        $xml .= '  </testsuite>' . PHP_EOL;
        $xml .= '</testsuites>';
        $this->line($xml);
    }
}
