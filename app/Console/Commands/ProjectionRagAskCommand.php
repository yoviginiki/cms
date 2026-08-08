<?php

namespace App\Console\Commands;

use App\Console\Commands\Migration\ResolvesSiteForCli;
use App\Domain\Projection\Rag\EmbedderFactory;
use App\Domain\Projection\Rag\HashEmbedder;
use App\Domain\Projection\Rag\SumiAssistant;
use App\Domain\Projection\Rag\VoyageEmbedder;
use Illuminate\Console\Command;

/**
 * Sumi (RAG) — ask a grounded question over a site's indexed content. Run
 * `projection:rag:index` first. Semantic retrieval requires Voyage; without it
 * the offline HashEmbedder is used (non-semantic).
 */
class ProjectionRagAskCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'projection:rag:ask {site : site slug or id} {question} {--k=5 : passages to retrieve}';

    protected $description = 'Ask Sumi a grounded question over a site indexed content';

    public function handle(SumiAssistant $sumi): int
    {
        $site = $this->resolveSite((string) $this->argument('site'));
        if (! $site) {
            $this->error('Site not found: ' . $this->argument('site'));

            return self::FAILURE;
        }

        $embedder = app(EmbedderFactory::class)->make();

        $result = $sumi->answer($site->id, (string) $this->argument('question'), $embedder, (int) $this->option('k'));

        $this->line($result['answer']);
        if ($result['sources'] !== []) {
            $this->newLine();
            $this->info('Sources:');
            foreach ($result['sources'] as $source) {
                $trail = $source['heading_path'] ? ' (' . implode(' › ', $source['heading_path']) . ')' : '';
                $this->line("  [{$source['address']}]{$trail}");
            }
        }

        return self::SUCCESS;
    }
}
