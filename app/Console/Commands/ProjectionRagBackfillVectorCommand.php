<?php

namespace App\Console\Commands;

use App\Console\Commands\Migration\ResolvesSiteForCli;
use App\Models\RagChunkRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill the additive pgvector column from the existing jsonb embeddings — no
 * re-embedding, so zero Voyage cost: it copies the exact same float array into
 * `embedding_vec`. Only rows whose embedding length matches the vector(N) column
 * are copied; shorter offline (hash-16) rows are skipped and keep using the jsonb
 * path. Idempotent — only touches rows where embedding_vec is still null.
 */
class ProjectionRagBackfillVectorCommand extends Command
{
    use ResolvesSiteForCli;

    protected $signature = 'projection:rag:backfill-vector {site : site slug or id}';

    protected $description = 'Copy existing jsonb embeddings into the pgvector column (no re-embedding)';

    public function handle(): int
    {
        if (! Schema::hasColumn('rag_chunks', 'embedding_vec')) {
            $this->error('rag_chunks.embedding_vec does not exist — run the pgvector migration first.');

            return self::FAILURE;
        }

        $site = $this->resolveSite((string) $this->argument('site'));
        if (! $site) {
            $this->error('Site not found: ' . $this->argument('site'));

            return self::FAILURE;
        }

        $filled = 0;
        $skipped = 0;

        RagChunkRecord::where('site_id', $site->id)
            ->whereNull('embedding_vec')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$filled, &$skipped) {
                foreach ($rows as $row) {
                    $embedding = $row->embedding ?? [];
                    if (count($embedding) !== RagChunkRecord::VECTOR_DIMS) {
                        $skipped++;
                        continue;
                    }
                    DB::update(
                        'UPDATE rag_chunks SET embedding_vec = ?::vector WHERE id = ?',
                        [RagChunkRecord::vectorLiteral($embedding), $row->id],
                    );
                    $filled++;
                }
            });

        $this->info(
            "Backfilled {$filled} vector(s) for {$site->slug}; skipped {$skipped} "
            . '(dimension != ' . RagChunkRecord::VECTOR_DIMS . ').'
        );

        return self::SUCCESS;
    }
}
