<?php

namespace App\Domain\Import\Jobs;

use App\Domain\Import\Services\WordPressImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecuteImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public string $siteId,
        public string $importId,
        public string $xmlPath,
        public array $options = [],
        public string $tenantId = '',
    ) {
    }

    public function handle(WordPressImporter $importer): void
    {
        if ($this->tenantId) {
            $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
            DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");
        }

        $site = \App\Models\Site::findOrFail($this->siteId);

        $this->updateStatus('running', 'Starting import...', ['step' => 'init', 'progress' => 0]);

        try {
            // Pass a progress callback to the importer
            $progressCallback = function (string $step, string $message, int $percent, array $counts = []) {
                $this->updateStatus('running', $message, [
                    'step' => $step,
                    'progress' => $percent,
                    'counts' => $counts,
                ]);
            };

            $result = $importer->importWithProgress($site, $this->xmlPath, $this->options, $progressCallback);

            $this->updateStatus('completed', 'Import completed successfully!', [
                'step' => 'done',
                'progress' => 100,
                'result' => $result->toArray(),
                'completed_at' => now()->toISOString(),
            ]);

            Log::info("WordPress import completed for site {$this->siteId}", $result->toArray());
        } catch (\Throwable $e) {
            $this->updateStatus('failed', $e->getMessage(), [
                'step' => 'error',
                'progress' => 0,
                'error' => $e->getMessage(),
                'completed_at' => now()->toISOString(),
            ]);

            Log::error("WordPress import failed for site {$this->siteId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            if (file_exists($this->xmlPath)) {
                @unlink($this->xmlPath);
            }
        }
    }

    private function updateStatus(string $status, string $message, array $extra = []): void
    {
        $existing = Cache::get("import:{$this->importId}", []);

        $data = array_merge($existing, [
            'status' => $status,
            'message' => $message,
            'updated_at' => now()->toISOString(),
        ], $extra);

        Cache::put("import:{$this->importId}", $data, now()->addHours(2));
    }
}
