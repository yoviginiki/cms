<?php

namespace App\Console\Commands;

use App\Domain\System\Services\CmsExportService;
use Illuminate\Console\Command;

class CmsExportCommand extends Command
{
    protected $signature = 'cms:export';

    protected $description = 'Pack the whole CMS source into storage/app/cms-export.zip (what the admin sidebar downloads)';

    public function handle(CmsExportService $export): int
    {
        $r = $export->build();
        $this->info(sprintf('%s — %d files, %.1f MB (skipped %d stale admin chunks)', $r['path'], $r['files'], $r['bytes'] / 1048576, $r['skipped_chunks']));

        return self::SUCCESS;
    }
}
