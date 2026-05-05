<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemLayoutsSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = require config_path('system-layouts.php');

        // Temporarily disable RLS for system layouts (tenant_id = NULL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE layouts DISABLE ROW LEVEL SECURITY');
        }

        foreach ($definitions as $def) {
            $existing = DB::table('layouts')
                ->where('slug', $def['slug'])
                ->whereNull('tenant_id')
                ->first();

            $data = [
                'name' => $def['name'],
                'description' => $def['description'],
                'wrapper_blade_view' => $def['wrapper_blade_view'],
                'supports' => json_encode($def['supports']),
                'allowed_block_types' => $def['allowed_block_types'] ? json_encode($def['allowed_block_types']) : null,
                'promoted_block_types' => $def['promoted_block_types'] ? json_encode($def['promoted_block_types']) : null,
                'default_block_stack' => $def['default_block_stack'] ? json_encode($def['default_block_stack']) : null,
                'assets' => $def['assets'] ? json_encode($def['assets']) : null,
                'config' => $def['config'] ? json_encode($def['config']) : null,
                'is_system' => true,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('layouts')->where('id', $existing->id)->update($data);
            } else {
                DB::table('layouts')->insert(array_merge($data, [
                    'id' => Str::uuid(),
                    'tenant_id' => null,
                    'parent_layout_id' => null,
                    'slug' => $def['slug'],
                    'created_at' => now(),
                ]));
            }
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE layouts ENABLE ROW LEVEL SECURITY');
        }

        $this->command?->info('Seeded ' . count($definitions) . ' system layouts.');
    }
}
