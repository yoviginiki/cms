<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\SeoService;
use App\Domain\Publishing\Services\SitemapGenerator;
use App\Domain\Publishing\Services\RobotsGenerator;
use App\Events\DeploymentProgressEvent;
use App\Models\Deployment;
use App\Models\PageVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

class PublishSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Deployment $deployment,
        public string $type = 'partial',
        public ?Deployment $rollbackTarget = null,
    ) {
    }

    public function handle(
        BuildPageService $buildService,
        DeployService $deployService,
        SitemapGenerator $sitemapGenerator,
        RobotsGenerator $robotsGenerator,
    ): void {
        $site = $this->deployment->site;
        $site->load('theme');
        $stagingPath = storage_path("app/builds/{$this->deployment->id}");

        try {
            $this->updateStatus('building', 'Starting build...');
            File::ensureDirectoryExists($stagingPath);

            // Get publishable content
            $pages = $site->pages()->where('status', 'published')->orderBy('sort_order')->get();
            $posts = $site->posts()->where('status', 'published')->orderByDesc('published_at')->get();

            $totalItems = $pages->count() + $posts->count();
            $this->deployment->update(['metadata' => array_merge(
                $this->deployment->metadata ?? [],
                ['pages_total' => $totalItems, 'pages_built' => 0]
            )]);

            $built = 0;

            // Build pages
            foreach ($pages as $page) {
                $html = $buildService->build($page, $site->theme, $site);
                $pagePath = $this->getPagePath($page);
                File::ensureDirectoryExists(dirname("{$stagingPath}/{$pagePath}"));
                File::put("{$stagingPath}/{$pagePath}", $html);

                // Create version snapshot
                $this->createVersion($page, 'page');

                $built++;
                $this->updateProgress($built, $totalItems, "Building page: {$page->title}");
            }

            // Build posts
            foreach ($posts as $post) {
                $html = $buildService->build($post, $site->theme, $site);
                $postPath = $this->getPostPath($post);
                File::ensureDirectoryExists(dirname("{$stagingPath}/{$postPath}"));
                File::put("{$stagingPath}/{$postPath}", $html);

                $this->createVersion($post, 'post');

                $built++;
                $this->updateProgress($built, $totalItems, "Building post: {$post->title}");
            }

            // Generate sitemap and robots.txt
            File::put("{$stagingPath}/sitemap.xml", $sitemapGenerator->generate($site));
            File::put("{$stagingPath}/robots.txt", $robotsGenerator->generate($site));

            // Deploy
            $this->updateStatus('deploying', 'Deploying files...');
            $deployService->deploy($this->deployment, $stagingPath);

            // Mark live
            $this->deployment->update([
                'status' => 'live',
                'completed_at' => now(),
                'metadata' => array_merge($this->deployment->metadata ?? [], [
                    'current_step' => 'live',
                    'pages_built' => $totalItems,
                ]),
            ]);

            $this->broadcast('Published successfully!');

            // Clean old builds (keep last 3)
            $this->cleanOldBuilds();
        } catch (\Throwable $e) {
            $this->deployment->update([
                'status' => 'failed',
                'error_log' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                'completed_at' => now(),
            ]);
            $this->broadcast("Build failed: {$e->getMessage()}");
            throw $e;
        }
    }

    private function createVersion($content, string $type): void
    {
        $blocks = $content->blocks()->orderBy('order')->get()->toArray();
        $lastVersion = PageVersion::where("{$type}_id", $content->id)
            ->orderByDesc('version_number')
            ->first();

        PageVersion::create([
            "{$type}_id" => $content->id,
            'blocks_snapshot' => $blocks,
            'seo_snapshot' => $content->seo_meta ?? [],
            'published_by' => $this->deployment->triggered_by,
            'published_at' => now(),
            'version_number' => ($lastVersion?->version_number ?? 0) + 1,
        ]);
    }

    private function getPagePath($page): string
    {
        $slug = $page->slug === 'home' ? '' : $page->slug;
        return ($slug ? "{$slug}/" : '') . 'index.html';
    }

    private function getPostPath($post): string
    {
        return "blog/{$post->slug}/index.html";
    }

    private function updateStatus(string $status, string $message): void
    {
        $this->deployment->update([
            'status' => $status,
            'started_at' => $this->deployment->started_at ?? now(),
            'metadata' => array_merge($this->deployment->metadata ?? [], ['current_step' => $status]),
        ]);
        $this->broadcast($message);
    }

    private function updateProgress(int $built, int $total, string $message): void
    {
        $this->deployment->update([
            'metadata' => array_merge($this->deployment->metadata ?? [], [
                'pages_built' => $built,
                'pages_total' => $total,
            ]),
        ]);
        $this->broadcast($message);
    }

    private function broadcast(string $message): void
    {
        try {
            event(new DeploymentProgressEvent(
                $this->deployment->site_id,
                $this->deployment->id,
                $this->deployment->status,
                $message,
                $this->deployment->metadata ?? [],
            ));
        } catch (\Throwable) {
            // Broadcasting may be disabled
        }
    }

    private function cleanOldBuilds(): void
    {
        $buildPath = storage_path('app/builds');
        if (!File::isDirectory($buildPath)) return;

        $dirs = collect(File::directories($buildPath))
            ->sortByDesc(fn($d) => File::lastModified($d))
            ->values();

        foreach ($dirs->slice(3) as $old) {
            File::deleteDirectory($old);
        }
    }
}
