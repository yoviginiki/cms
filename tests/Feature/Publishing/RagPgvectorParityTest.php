<?php

namespace Tests\Feature\Publishing;

use App\Domain\Projection\Rag\HashEmbedder;
use App\Domain\Projection\Rag\RagChunk;
use App\Domain\Projection\Rag\RagStore;
use App\Domain\Projection\Rag\Retrieval\JsonbCosineRetriever;
use App\Domain\Projection\Rag\Retrieval\PgvectorRetriever;
use App\Models\RagChunkRecord;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

/**
 * The dual-path parity gate. The same query, run through the jsonb PHP-cosine
 * retriever and the pgvector `<=>` retriever, must select the same top-K chunks
 * with matching cosine scores. Materially different results = divergence (the
 * migration rule "разминаване = спираш"). A 1024-dim HashEmbedder populates both
 * the jsonb and vector(1024) columns with identical vectors, so the only allowed
 * difference is float4-storage rounding in the vector column, bounded by epsilon.
 * Exact ordering among epsilon-tied scores is not asserted — that is not a
 * meaningful divergence; membership, per-chunk scores, and the top-1 hit are.
 */
class RagPgvectorParityTest extends TestCase
{
    /** vector(N) stores float4; jsonb keeps float8 — bound the score delta. */
    private const SCORE_EPSILON = 1e-4;

    /** @var array<string,string> */
    private const TEXTS = [
        's1' => 'The projection is the single source of truth for machine-readable views.',
        's2' => 'Publishing produces flat static HTML that scores 100 on PageSpeed.',
        's3' => 'Sumi retrieves grounded passages and cites their addresses.',
        's4' => 'Row level security isolates every tenant at the database layer.',
        's5' => 'The editor writes blocks; the projection derives segments from them.',
        's6' => 'Redis queues drive incremental reindexing on publish.',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise the dual-write / vector read path (default kill-switch is OFF).
        config(['cms.sumi.pgvector' => true]);
    }

    private function seedChunks(Site $site, HashEmbedder $embedder): void
    {
        $store = app(RagStore::class);
        $chunks = [];
        foreach (self::TEXTS as $id => $text) {
            $chunks[] = new RagChunk(
                id: $id,
                pageId: '',
                pageVersionId: '',
                address: "{$id}#content",
                headingPath: ['Guide'],
                text: $text,
                hash: hash('sha256', $text),
                embedding: $embedder->embed($text),
                model: $embedder->model(),
            );
        }
        $store->store($site->id, $chunks);
    }

    public function test_jsonb_and_pgvector_paths_return_equivalent_results(): void
    {
        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);

        // 1024-dim embedder → RagStore dual-writes the vector column too.
        $embedder = new HashEmbedder(RagChunkRecord::VECTOR_DIMS);
        $this->seedChunks($site, $embedder);

        // The dual-write path was actually exercised (vector column populated).
        $this->assertSame(
            count(self::TEXTS),
            RagChunkRecord::where('site_id', $site->id)->whereNotNull('embedding_vec')->count(),
            'dual-write did not populate embedding_vec for every chunk'
        );

        $jsonb = app(JsonbCosineRetriever::class);
        $pgvec = app(PgvectorRetriever::class);
        $topK = count(self::TEXTS); // include all → membership is comparable without a K-boundary tie

        // An exact-text query has cosine 1.0 with its own chunk → unambiguous top-1.
        $queries = [
            self::TEXTS['s1'],
            self::TEXTS['s4'],
            'tenant isolation at the database',
            'how does publishing produce static html',
            'zzz completely unrelated nonsense query',
        ];

        foreach ($queries as $q) {
            $qe = $embedder->embed($q);
            $a = $jsonb->retrieve($site->id, $qe, $topK);
            $b = $pgvec->retrieve($site->id, $qe, $topK);

            $idsA = array_map(fn ($h) => $h['chunk']->segment_id, $a);
            $idsB = array_map(fn ($h) => $h['chunk']->segment_id, $b);

            // Same top-K members (order-independent — float4 may reorder epsilon-ties).
            $this->assertEqualsCanonicalizing($idsA, $idsB, "top-K membership diverged for: {$q}");

            // Strongest signal: the #1 hit is the same on both paths.
            $this->assertSame($idsA[0], $idsB[0], "top-1 diverged for: {$q}");

            // Scores agree within float4 epsilon, matched by segment id.
            $scoreB = [];
            foreach ($b as $hit) {
                $scoreB[$hit['chunk']->segment_id] = $hit['score'];
            }
            foreach ($a as $hit) {
                $id = $hit['chunk']->segment_id;
                $this->assertEqualsWithDelta(
                    $hit['score'],
                    $scoreB[$id],
                    self::SCORE_EPSILON,
                    "score diverged beyond epsilon for {$q} / {$id}"
                );
            }
        }
    }

    public function test_dual_write_is_skipped_when_kill_switch_off(): void
    {
        config(['cms.sumi.pgvector' => false]); // override the setUp default

        $this->setTenantScope($this->owner);
        $site = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->seedChunks($site, new HashEmbedder(RagChunkRecord::VECTOR_DIMS));

        // Rows are stored (jsonb path), but the vector column is left untouched.
        $this->assertSame(count(self::TEXTS), RagChunkRecord::where('site_id', $site->id)->count());
        $this->assertSame(
            0,
            RagChunkRecord::where('site_id', $site->id)->whereNotNull('embedding_vec')->count(),
            'dual-write ran despite the kill-switch being off'
        );
    }

    public function test_pgvector_retriever_respects_tenant_isolation(): void
    {
        $embedder = new HashEmbedder(RagChunkRecord::VECTOR_DIMS);

        // Tenant A (the base tenant) indexes a site.
        $this->setTenantScope($this->owner);
        $siteA = Site::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->seedChunks($siteA, $embedder);

        // A separate tenant B with its own site + chunks.
        $tenantB = Tenant::factory()->create();
        $ownerB = User::factory()->owner()->create(['tenant_id' => $tenantB->id]);
        $this->setTenantScope($ownerB);
        $siteB = Site::factory()->create(['tenant_id' => $tenantB->id]);
        $this->seedChunks($siteB, $embedder);

        $qe = $embedder->embed('the projection single source of truth');
        $pgvec = app(PgvectorRetriever::class);

        // Under tenant B's scope: sees B, and a forged request for A's rows sees nothing (RLS).
        $this->assertNotEmpty($pgvec->retrieve($siteB->id, $qe, 5));
        $this->assertSame([], $pgvec->retrieve($siteA->id, $qe, 5), 'tenant B reached tenant A rows via pgvector');

        // Back under tenant A: mirror check.
        $this->setTenantScope($this->owner);
        $this->assertNotEmpty($pgvec->retrieve($siteA->id, $qe, 5));
        $this->assertSame([], $pgvec->retrieve($siteB->id, $qe, 5), 'tenant A reached tenant B rows via pgvector');
    }
}
