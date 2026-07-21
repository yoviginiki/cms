<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Publishing\Services\PublishOrchestrator;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessScheduledContentJob
{
    public function __invoke(): void
    {
        // Process across all tenants
        $sites = Site::withoutGlobalScopes()
            ->where('status', 'active')
            ->get();

        foreach ($sites as $site) {
            $this->processSite($site);
        }
    }

    private function processSite(Site $site): void
    {
        // Set RLS context
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $site->tenant_id);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");

        $publishNeeded = false;

        // Process scheduled pages
        $pages = Page::withoutGlobalScopes()
            ->where('site_id', $site->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($pages as $page) {
            $page->update([
                'status' => 'published',
                'published_at' => $page->scheduled_at,
                'scheduled_at' => null,
            ]);
            $publishNeeded = true;
            Log::info("Scheduled page published: {$page->title}");
        }

        // Process scheduled posts
        $posts = Post::withoutGlobalScopes()
            ->where('site_id', $site->id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($posts as $post) {
            $post->update([
                'status' => 'published',
                'published_at' => $post->scheduled_at,
                'scheduled_at' => null,
            ]);
            $publishNeeded = true;
            Log::info("Scheduled post published: {$post->title}");
        }

        // Collection records: publish_at / unpublish_at windows. Direct status
        // flips + needs_republish so the partial publish below rebuilds the
        // record pages, archives and search index.
        $dueRecords = \App\Models\Record::withoutGlobalScopes()
            ->where('site_id', $site->id)
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
            $publishNeeded = true;
            Log::info("Scheduled record published: {$record->title}");
        }

        $expiredRecords = \App\Models\Record::withoutGlobalScopes()
            ->where('site_id', $site->id)
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
            $publishNeeded = true;
            Log::info("Scheduled record unpublished: {$record->title}");
        }

        // Trigger site re-publish if any content was published
        if ($publishNeeded) {
            try {
                $owner = User::withoutGlobalScopes()
                    ->where('tenant_id', $site->tenant_id)
                    ->where('role', 'owner')
                    ->first();

                if ($owner) {
                    $orchestrator = app(PublishOrchestrator::class);
                    $orchestrator->publish($site, $owner, 'partial');
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to auto-publish after scheduled content: {$e->getMessage()}");
            }
        }
    }
}
