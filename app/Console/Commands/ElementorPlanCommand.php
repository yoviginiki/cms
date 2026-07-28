<?php

namespace App\Console\Commands;

use App\Domain\Migration\Services\ElementorImportPlanner;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Assisted planning for `elementor:import`: connect once with WP DB creds
 * (never stored), enumerate the Elementor pages, and print the ready-to-run
 * import command with the id→slug mapping. The tedious part, automated.
 */
class ElementorPlanCommand extends Command
{
    protected $signature = 'elementor:plan
        {--site= : Target site UUID or slug}
        {--tenant= : Tenant UUID (RLS, before site lookup)}
        {--wp-db= : WordPress database name}
        {--wp-user= : WordPress database user}
        {--wp-pass= : WordPress database password}
        {--wp-prefix=wp_ : WordPress table prefix}
        {--origin= : Source site base URL}';

    protected $description = 'Enumerate a WP site\'s Elementor pages and print the ready elementor:import command';

    public function handle(ElementorImportPlanner $planner): int
    {
        if ($this->option('tenant')) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->option('tenant')]);
        }

        $target = (string) $this->option('site');
        $site = preg_match('/^[0-9a-f-]{36}$/i', $target) === 1
            ? Site::find($target)
            : Site::where('slug', $target)->first();
        if (!$site) {
            $this->error('Site not found: ' . $target);

            return self::FAILURE;
        }
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$site->tenant_id]);

        try {
            $plan = $planner->plan($site, [
                'wp_db' => (string) $this->option('wp-db'),
                'wp_user' => (string) $this->option('wp-user'),
                'wp_pass' => (string) $this->option('wp-pass'),
                'wp_prefix' => (string) $this->option('wp-prefix'),
                'origin' => (string) $this->option('origin'),
            ]);
        } catch (\Throwable $e) {
            $this->error('Could not read the WordPress database: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($plan['pages'] === []) {
            $this->warn('No Elementor-built pages found for that prefix.');

            return self::FAILURE;
        }

        $this->table(
            ['WP id', 'slug', 'title', 'matches existing?'],
            array_map(fn ($p) => [$p['wpId'], $p['slug'], mb_substr($p['title'], 0, 40), $p['matched'] ? 'yes' : '—'], $plan['pages'])
        );
        $this->line('posts available: ' . $plan['postsAvailable'] . ($plan['catalogPostId'] ? ', catalog post: ' . $plan['catalogPostId'] : ''));
        $this->newLine();
        $this->info('Ready import command (fill in the password):');
        $this->line($plan['command']);

        return self::SUCCESS;
    }
}
