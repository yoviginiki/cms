<?php

namespace App\Console\Commands;

use App\Console\Commands\Migration\ResolvesSiteForCli;
use App\Domain\Projection\ProjectionPublisher;
use App\Domain\Projection\Rag\EmbedderFactory;
use App\Domain\Projection\Rag\HashEmbedder;
use App\Domain\Projection\Rag\RagIndexer;
use App\Domain\Projection\Rag\RagStore;
use App\Domain\Projection\Rag\VoyageEmbedder;
use App\Domain\Publishing\Services\LocalePaths;
use Illuminate\Console\Command;

/**
 * Sumi (RAG) — index a site's content into the chunk store. Uses Voyage when
 * configured, otherwise the deterministic HashEmbedder (non-semantic, for
 * development). Incremental: only new content hashes are embedded and stored.
 */
class ProjectionRagIndexCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'projection:rag:index
        {site : site slug or id}
        {--dims= : override hash embedder dimensions (hash provider only)}';

    protected $description = 'Index a site content into the RAG chunk store (Voyage or offline HashEmbedder)';

    public function handle(ProjectionPublisher $publisher, RagStore $store): int
    {
        $site = $this->resolveSite((string) $this->argument('site'));
        if (! $site) {
            $this->error('Site not found: ' . $this->argument('site'));

            return self::FAILURE;
        }

        // --dims only applies to the hash embedder; let it override the config.
        if ($this->option('dims') !== null) {
            config(['cms.sumi.hash_dims' => (int) $this->option('dims')]);
        }
        $embedder = app(EmbedderFactory::class)->make();
        $indexer = new RagIndexer($embedder);

        $written = 0;
        foreach ($site->pages()->where('status', 'published')->get() as $page) {
            $url = $publisher->urlForPath(LocalePaths::pagePath($site, $page));
            $written += $store->store($site->id, $indexer->index($publisher->build($site, $page, $url)));
        }
        foreach ($site->posts()->where('status', 'published')->get() as $post) {
            $url = $publisher->urlForPath(LocalePaths::postPath($site, $post));
            $written += $store->store($site->id, $indexer->index($publisher->build($site, $post, $url)));
        }

        $this->info("Indexed {$written} new chunk(s) for {$site->slug} using {$embedder->model()}.");

        return self::SUCCESS;
    }
}
