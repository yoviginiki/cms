<?php

namespace App\Console\Commands\Migration;

use App\Domain\Migration\Services\RedirectMapGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrationRedirectsCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'migration:redirects
        {site : Site slug or id}
        {origin : Origin base URL}
        {--deploy : Also copy .htaccess into the site\'s live docroot}';

    protected $description = 'Generate 301 redirect maps (.htaccess + nginx include) from every origin URL to its migrated counterpart — no SEO is lost';

    public function handle(RedirectMapGenerator $generator): int
    {
        $site = $this->resolveSite($this->argument('site'));
        if (!$site) {
            $this->error('Site not found.');

            return self::FAILURE;
        }

        $result = $generator->generate($site, $this->argument('origin'));

        $dir = storage_path("app/migration/{$site->slug}");
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/redirects.htaccess", $result['htaccess']);
        File::put("{$dir}/redirects.nginx.conf", $result['nginx']);
        File::put("{$dir}/redirects.json", json_encode([
            'mapped' => $result['mapped'],
            'unmapped' => $result['unmapped'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->info(count($result['mapped']) . " redirects written to {$dir}");
        foreach ($result['unmapped'] as $u) {
            $this->warn("no target for: {$u}");
        }

        if ($this->option('deploy')) {
            $docroot = $site->custom_domain
                ? config('publishing.tenant_base') . '/' . $site->custom_domain . '/public_html'
                : config('publishing.public_path') . '/' . $site->slug;
            if (is_dir($docroot)) {
                File::put("{$docroot}/.htaccess", $result['htaccess']);
                $this->info("deployed .htaccess to {$docroot}");
            } else {
                $this->warn("docroot not found: {$docroot} — publish the site first");
            }
        }

        return self::SUCCESS;
    }
}
