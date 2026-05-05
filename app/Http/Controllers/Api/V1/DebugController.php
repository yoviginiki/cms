<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;

class DebugController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $tenantId = $request->user()->tenant_id;
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");
        $site = Site::first();

        // Database health
        $dbOk = true;
        $dbLatency = 0;
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $dbLatency = round((microtime(true) - $start) * 1000, 1);
        } catch (\Throwable) {
            $dbOk = false;
        }

        // Redis health
        $redisOk = false;
        $redisLatency = 0;
        $redisMemory = null;
        $redisKeys = 0;
        if (config('cms.redis_enabled')) {
            try {
                $start = microtime(true);
                Redis::ping();
                $redisLatency = round((microtime(true) - $start) * 1000, 1);
                $redisOk = true;
                $info = Redis::info();
                $redisMemory = $info['used_memory_human'] ?? null;
                $redisKeys = (int) ($info['db' . config('cms.redis.db_default', 0)]['keys'] ?? 0);
            } catch (\Throwable) {}
        }

        // Queue health
        $pendingJobs = 0;
        $failedJobs = 0;
        $oldestPending = null;
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $oldest = DB::table('jobs')->orderBy('created_at')->first();
            if ($oldest) {
                $oldestPending = now()->diffForHumans(now()->subSeconds(time() - $oldest->created_at), true) . ' ago';
            }
        } catch (\Throwable) {}

        // Worker status
        $workerLog = storage_path('logs/queue-worker.log');
        $workerActive = file_exists($workerLog) && filemtime($workerLog) > time() - 120;
        $lastJobLine = null;
        if (file_exists($workerLog)) {
            $tail = $this->tailFile($workerLog, 5);
            $lines = array_filter(explode("\n", trim($tail)));
            $lastJobLine = end($lines) ?: null;
        }

        // Recent failed job details
        $recentFailed = [];
        try {
            $failed = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(5)->get();
            foreach ($failed as $f) {
                $payload = json_decode($f->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                $errorLines = explode("\n", $f->exception ?? '');
                $errorMsg = $errorLines[0] ?? '';
                $recentFailed[] = [
                    'id' => $f->uuid,
                    'job' => class_basename($jobClass),
                    'error' => substr($errorMsg, 0, 200),
                    'failed_at' => $f->failed_at,
                ];
            }
        } catch (\Throwable) {}

        // Disk usage
        $storagePath = storage_path();
        $diskFree = null;
        $diskTotal = null;
        try {
            $diskFree = $this->formatBytes((int) disk_free_space($storagePath));
            $diskTotal = $this->formatBytes((int) disk_total_space($storagePath));
        } catch (\Throwable) {}

        // PHP info
        $phpExtensions = get_loaded_extensions();
        $requiredExts = ['pdo', 'pdo_pgsql', 'mbstring', 'openssl', 'xml', 'gd', 'curl', 'redis', 'fileinfo'];
        $missingExts = array_diff($requiredExts, array_map('strtolower', $phpExtensions));

        // Open basedir
        $openBasedir = ini_get('open_basedir') ?: 'none (unrestricted)';

        // Config checks
        $configIssues = [];
        if (config('app.debug')) $configIssues[] = 'APP_DEBUG is ON — should be OFF in production';
        if (config('app.env') !== 'production') $configIssues[] = 'APP_ENV is "' . config('app.env') . '" — should be "production"';
        if (empty(config('app.key'))) $configIssues[] = 'APP_KEY is empty — run php artisan key:generate';
        if (!config('session.secure')) $configIssues[] = 'SESSION_SECURE_COOKIE is false — should be true for HTTPS';

        // Deployment stats
        $deployStats = null;
        if ($site) {
            $totalDeploys = Deployment::where('site_id', $site->id)->count();
            $liveDeploys = Deployment::where('site_id', $site->id)->where('status', 'live')->count();
            $failedDeploys = Deployment::where('site_id', $site->id)->where('status', 'failed')->count();
            $lastDeploy = Deployment::where('site_id', $site->id)->orderByDesc('created_at')->first();
            $deployStats = [
                'total' => $totalDeploys,
                'live' => $liveDeploys,
                'failed' => $failedDeploys,
                'last_status' => $lastDeploy?->status,
                'last_at' => $lastDeploy?->created_at?->toISOString(),
                'last_duration' => $lastDeploy && $lastDeploy->started_at && $lastDeploy->completed_at
                    ? $lastDeploy->started_at->diffInSeconds($lastDeploy->completed_at) . 's'
                    : null,
                'last_error' => $lastDeploy?->status === 'failed' ? substr($lastDeploy->error_log ?? '', 0, 300) : null,
            ];
        }

        // Content health checks
        $contentIssues = [];
        if ($site) {
            $orphanBlocks = DB::table('blocks')
                ->whereNotIn('blockable_id', $site->pages()->pluck('id')->merge($site->posts()->pluck('id')))
                ->where('blockable_type', 'page')
                ->count();
            if ($orphanBlocks > 0) $contentIssues[] = "{$orphanBlocks} orphan blocks (belong to deleted pages/posts)";

            $draftPages = $site->pages()->where('status', 'draft')->count();
            if ($draftPages > 0) $contentIssues[] = "{$draftPages} draft pages (not published)";

            $noBlockPages = $site->pages()->where('status', 'published')
                ->whereDoesntHave('blocks')->count();
            if ($noBlockPages > 0) $contentIssues[] = "{$noBlockPages} published pages with no blocks (empty)";

            $noMenuSite = $site->menus()->count() === 0;
            if ($noMenuSite) $contentIssues[] = 'No menus configured — site has no navigation';

            $homepageId = $site->settings['homepage_id'] ?? null;
            if (!$homepageId && !$site->pages()->where('slug', 'home')->exists()) {
                $contentIssues[] = 'No homepage set and no page with slug "home" exists';
            }
        }

        return response()->json(['data' => [
            'health' => [
                'database' => ['ok' => $dbOk, 'latency_ms' => $dbLatency, 'driver' => config('database.default')],
                'redis' => ['ok' => $redisOk, 'latency_ms' => $redisLatency, 'memory' => $redisMemory, 'keys' => $redisKeys, 'enabled' => config('cms.redis_enabled')],
                'queue' => [
                    'worker_active' => $workerActive,
                    'pending' => $pendingJobs,
                    'failed' => $failedJobs,
                    'oldest_pending' => $oldestPending,
                    'last_job' => $lastJobLine,
                    'driver' => config('queue.default'),
                ],
                'disk' => ['free' => $diskFree, 'total' => $diskTotal],
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'cms_version' => config('cms.version', '1.0.0'),
                'app_env' => config('app.env'),
                'debug_mode' => config('app.debug'),
                'open_basedir' => $openBasedir,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time') . 's',
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'timezone' => config('app.timezone'),
                'missing_extensions' => $missingExts,
                'config_issues' => $configIssues,
            ],
            'site' => $site ? [
                'id' => $site->id,
                'name' => $site->name,
                'domain' => $site->custom_domain,
                'pages' => $site->pages()->count(),
                'published_pages' => $site->pages()->where('status', 'published')->count(),
                'posts' => $site->posts()->count(),
                'published_posts' => $site->posts()->where('status', 'published')->count(),
                'categories' => $site->categories()->count(),
                'tags' => $site->tags()->count(),
                'assets' => $site->assets()->count(),
                'blocks' => DB::table('blocks')->count(),
                'grids' => $site->grids()->count(),
                'menus' => $site->menus()->count(),
                'menu_items' => DB::table('menu_items')->whereIn('menu_id', $site->menus()->pluck('id'))->count(),
                'active_theme' => $site->theme?->name ?? 'none',
                'auto_publish' => ($site->settings['auto_publish'] ?? true) === true,
                'homepage_id' => $site->settings['homepage_id'] ?? null,
            ] : null,
            'deploy' => $deployStats,
            'failed_jobs' => $recentFailed,
            'content_issues' => $contentIssues,
            'recent_errors' => $this->getRecentErrors(15),
            'storage' => [
                'assets' => $this->dirSize(storage_path('app/assets')),
                'builds' => $this->dirSize(storage_path('app/builds')),
                'imports' => $this->dirSize(storage_path('app/imports')),
                'logs' => file_exists(storage_path('logs/laravel.log'))
                    ? $this->formatBytes(filesize(storage_path('logs/laravel.log'))) : '0 B',
            ],
        ]]);
    }

    public function logs(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $level = $request->input('level', 'all');
        $logFile = storage_path('logs/laravel.log');
        if (!file_exists($logFile)) {
            return response()->json(['data' => ['entries' => [], 'stats' => []]]);
        }

        $content = $this->tailFile($logFile, $request->integer('lines', 100));

        $entries = [];
        $current = null;
        $stats = ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(ERROR|WARNING|INFO|DEBUG): (.*)/', $line, $m)) {
                if ($current) $entries[] = $current;
                $lvl = strtolower($m[3]);
                $stats[$lvl] = ($stats[$lvl] ?? 0) + 1;
                $current = [
                    'timestamp' => $m[1],
                    'channel' => $m[2],
                    'level' => $lvl,
                    'message' => $m[4],
                    'trace' => '',
                ];
            } elseif ($current) {
                if (str_starts_with($line, '#') || str_starts_with($line, '  at ') || str_starts_with($line, '  ')) {
                    $current['trace'] .= $line . "\n";
                } elseif (strlen($line) < 300) {
                    $current['message'] .= "\n" . $line;
                }
            }
        }
        if ($current) $entries[] = $current;

        $entries = array_reverse($entries);
        if ($level !== 'all') {
            $entries = array_values(array_filter($entries, fn($e) => $e['level'] === $level));
        }

        return response()->json(['data' => ['entries' => array_slice($entries, 0, 100), 'stats' => $stats]]);
    }

    public function clearLogs(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) file_put_contents($logFile, '');
        return response()->json(['message' => 'Logs cleared']);
    }

    public function retryFailedJobs(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        \Illuminate\Support\Facades\Artisan::call('queue:retry', ['id' => 'all']);
        return response()->json(['message' => 'Failed jobs queued for retry']);
    }

    public function flushFailedJobs(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        DB::table('failed_jobs')->truncate();
        return response()->json(['message' => 'All failed jobs cleared']);
    }

    public function cacheStatus(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $configCached = file_exists(base_path('bootstrap/cache/config.php'));
        $routesCached = file_exists(base_path('bootstrap/cache/routes-v7.php'));
        $viewsCached = !empty(glob(storage_path('framework/views/*.php')));

        return response()->json(['data' => [
            'config_cached' => $configCached,
            'routes_cached' => $routesCached,
            'views_cached' => $viewsCached,
        ]]);
    }

    public function clearCache(Request $request): JsonResponse
    {
        if (!$request->user()?->hasMinimumRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $type = $request->input('type', 'all');
        $cleared = [];

        if ($type === 'all' || $type === 'config') {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            $cleared[] = 'config';
        }
        if ($type === 'all' || $type === 'routes') {
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            $cleared[] = 'routes';
        }
        if ($type === 'all' || $type === 'views') {
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            $cleared[] = 'views';
        }
        if ($type === 'all' || $type === 'cache') {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            $cleared[] = 'cache';
        }

        return response()->json(['message' => 'Cleared: ' . implode(', ', $cleared)]);
    }

    private function isWorkerRunning(): bool
    {
        $pidFile = storage_path('logs/queue-worker.log');
        return file_exists($pidFile) && filemtime($pidFile) > time() - 120;
    }

    private function getRecentErrors(int $count): array
    {
        $logFile = storage_path('logs/laravel.log');
        if (!file_exists($logFile)) return [];
        $content = $this->tailFile($logFile, 500);
        $errors = [];
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.ERROR: (.{1,300})/', $line, $m)) {
                $errors[] = ['time' => $m[1], 'message' => $m[2]];
            }
        }
        return array_slice(array_reverse($errors), 0, $count);
    }

    private function tailFile(string $path, int $lines): string
    {
        if (!file_exists($path)) return '';
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $total = $file->key();
        $start = max(0, $total - $lines);
        $output = '';
        $file->seek($start);
        while (!$file->eof()) $output .= $file->fgets();
        return $output;
    }

    private function dirSize(string $path): string
    {
        if (!is_dir($path)) return '0 B';
        $bytes = 0;
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) $bytes += $f->getSize();
        } catch (\Throwable) {}
        return $this->formatBytes($bytes);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 1) . ' GB';
    }
}
