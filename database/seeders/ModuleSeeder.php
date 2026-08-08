<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * Registers the built-in modules. Idempotent (updateOrCreate on `key`) so it is
 * safe to re-run. Modules are seeded DISABLED — an operator turns them on from
 * Settings → Modules.
 */
class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        Module::updateOrCreate(
            ['key' => 'culture-engine'],
            [
                'name' => 'Culture Engine',
                'description' => 'Receives AI-curated cultural bulletins (weekly guides, '
                    . 'event listings) from the standalone ArtDay Culture Engine and files '
                    . 'them as drafts. Adds the bulletin-section and event-card blocks.',
                'enabled_globally' => false,
                'settings_schema' => [
                    'fields' => [
                        [
                            'key' => 'default_status',
                            'label' => 'Default draft status',
                            'type' => 'select',
                            'options' => ['draft'],
                            'default' => 'draft',
                            'help' => 'Incoming bulletins are always filed as drafts; auto-publish is not offered.',
                        ],
                    ],
                ],
            ],
        );
    }
}
