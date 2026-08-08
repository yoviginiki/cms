<?php

namespace App\Console\Commands;

use App\Console\Commands\Migration\ResolvesSiteForCli;
use App\Domain\Projection\Export\ProjectionExporter;
use App\Domain\Projection\ProjectionPublisher;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Export consumer (delivery: CLI). Renders a page/post's projection to JSON or
 * Markdown. Read-only — builds the projection fresh, never writes to content.
 */
class ProjectionExportCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'projection:export
        {site : site slug or id}
        {--page= : page slug or id}
        {--post= : post slug or id}
        {--format=json : json|md}
        {--out= : write to this file instead of stdout}';

    protected $description = 'Export a page or post projection as JSON or Markdown (read-only)';

    public function handle(ProjectionPublisher $publisher, ProjectionExporter $exporter): int
    {
        $format = strtolower((string) $this->option('format'));
        if (! in_array($format, ['json', 'md'], true)) {
            $this->error("Unknown format '{$format}' (expected json|md).");

            return self::INVALID;
        }

        $site = $this->resolveSite((string) $this->argument('site'));
        if (! $site) {
            $this->error('Site not found: ' . $this->argument('site'));

            return self::FAILURE;
        }

        $content = $this->resolveContent($site);
        if (! $content) {
            $this->error('Specify exactly one of --page or --post (by slug or id) that exists on this site.');

            return self::FAILURE;
        }

        $url = '/' . trim((string) ($content->slug ?? ''), '/');
        $url = $url === '/' ? '/' : $url . '/';
        $projection = $publisher->build($site, $content, $url);

        $output = $format === 'md'
            ? $exporter->toMarkdown($projection)
            : $exporter->toJson($projection);

        if ($out = $this->option('out')) {
            File::ensureDirectoryExists(dirname($out));
            File::put($out, $output);
            $this->info("Wrote {$format} export → {$out}");

            return self::SUCCESS;
        }

        $this->line($output);

        return self::SUCCESS;
    }

    private function resolveContent($site): Page|Post|null
    {
        $page = $this->option('page');
        $post = $this->option('post');

        if (($page && $post) || (! $page && ! $post)) {
            return null;
        }

        if ($page) {
            return Page::where('site_id', $site->id)
                ->where(Str::isUuid($page) ? 'id' : 'slug', $page)
                ->first();
        }

        return Post::where('site_id', $site->id)
            ->where(Str::isUuid($post) ? 'id' : 'slug', $post)
            ->first();
    }
}
