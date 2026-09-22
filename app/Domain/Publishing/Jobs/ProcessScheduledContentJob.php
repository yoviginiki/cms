<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled publishing (F20, audit 2026-09-22).
 *
 * Contract: a page/post is scheduled when it is `draft` with a `scheduled_at`
 * in the future (the schema/requests only know draft/published/archived —
 * the old job looked for a `scheduled` status that could never exist). Sites
 * are read per tenant with the tenant GUC set first: `withoutGlobalScopes()`
 * removes Eloquent scopes, not PostgreSQL RLS, so under the restricted runtime
 * role the old cross-tenant query saw nothing.
 *
 * Idempotent: `scheduled_at` is cleared in the same update that publishes,
 * so a second run finds nothing due. The publish request is durable: the
 * item is flagged `needs_republish`, so if the deployment cannot be created
 * now (one is active) or fails, the next successful deployment's follow-up
 * (AutoPublishService::followUp) or the stale-content flow carries it.
 */
class ProcessScheduledContentJob
{
    public function __invoke(): void
    {
        // Explicit per-tenant context; the caller's context is restored at the
        // end (the scheduler has none → cleared, a test/CLI caller keeps its own).
        $previous = \App\Domain\Tenancy\PublicTenantResolver::current();
        try {
            foreach (Tenant::pluck('id') as $tenantId) {
                \App\Domain\Tenancy\PublicTenantResolver::set((string) $tenantId);
                foreach (Site::where('status', 'active')->get() as $site) {
                    $this->processSite($site);
                }
            }
        } finally {
            $previous === '' ? \App\Domain\Tenancy\PublicTenantResolver::clear() : \App\Domain\Tenancy\PublicTenantResolver::set($previous);
        }
    }

    private function processSite(Site $site): void
    {
        $due = [];

        foreach (Page::where('site_id', $site->id)->where('status', 'draft')
            ->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())->get() as $page) {
            $page->forceFill([
                'status' => 'published',
                'published_at' => $page->scheduled_at,
                'scheduled_at' => null,
                'needs_republish' => true,
                'needs_republish_reason' => 'Scheduled publish',
            ])->save();
            $due['pages'][] = $page->id;
            Log::info("Scheduled page published: {$page->title}");
        }

        foreach (Post::where('site_id', $site->id)->where('status', 'draft')
            ->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())->get() as $post) {
            $post->forceFill([
                'status' => 'published',
                'published_at' => $post->scheduled_at,
                'scheduled_at' => null,
                'needs_republish' => true,
                'needs_republish_reason' => 'Scheduled publish',
            ])->save();
            $due['posts'][] = $post->id;
            Log::info("Scheduled post published: {$post->title}");
        }

        // Collection records: publish_at / unpublish_at windows. Direct status
        // flips + needs_republish so the publish below rebuilds the record
        // pages, archives and search index.
        $dueRecords = \App\Models\Record::where('site_id', $site->id)
            ->where('status', 'draft')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->get();
        foreach ($dueRecords as $record) {
            $record->update([
                'status' => 'published',
                'published_at' => $record->publish_at,
                'publish_at' => null,
                'needs_republish' => true,
                'needs_republish_reason' => 'record_scheduled_publish',
            ]);
            $due['records'][] = $record->id;
            Log::info("Scheduled record published: {$record->title}");
        }

        $expiredRecords = \App\Models\Record::where('site_id', $site->id)
            ->where('status', 'published')
            ->whereNotNull('unpublish_at')
            ->where('unpublish_at', '<=', now())
            ->get();
        foreach ($expiredRecords as $record) {
            $record->update([
                'status' => 'draft',
                'unpublish_at' => null,
                'needs_republish' => true,
                'needs_republish_reason' => 'record_scheduled_unpublish',
            ]);
            $due['records'][] = $record->id;
            Log::info("Scheduled record unpublished: {$record->title}");
        }

        if ($due === []) {
            return;
        }

        // One deployment for everything that became due. Through the same
        // gate as every publish; if one is active the flags above keep the
        // request alive for the follow-up.
        try {
            $owner = User::where('tenant_id', $site->tenant_id)->where('role', 'owner')->first()
                ?? User::where('tenant_id', $site->tenant_id)->where('role', 'admin')->first();
            if ($owner) {
                app(PublishOrchestrator::class)->publish($site, $owner, 'partial');
            }
        } catch (\Throwable $e) {
            Log::warning("Scheduled content: publish deferred for {$site->name}: {$e->getMessage()}");
        }
    }
}
