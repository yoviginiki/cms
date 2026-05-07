<?php

namespace App\Domain\Sites\Services;

use App\Domain\Grid\Services\GridPresetSeeder;
use App\Models\Category;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\Theme;
use Illuminate\Support\Str;

class SiteService
{
    public function __construct(
        private GridPresetSeeder $gridPresetSeeder,
    ) {}

    public function createSite(array $data, Tenant $tenant): Site
    {
        $data['tenant_id'] = $tenant->id;
        $data['slug'] = $data['slug'] ?? $this->generateUniqueSlug($data['name']);

        $site = Site::create($data);

        $theme = Theme::create([
            'site_id' => $site->id,
            'name' => 'Default',
            'slug' => 'default',
            'version' => '1.0.0',
            'config' => [
                'colors' => ['primary' => '#3b82f6', 'secondary' => '#64748b'],
                'fonts' => ['heading' => 'Inter', 'body' => 'Inter'],
            ],
            'manifest_json' => [],
            'template_path' => 'themes/default',
            'document' => [
                '$metadata' => ['name' => 'Default', 'version' => '1.0.0', 'modes' => ['light']],
                'primitive' => [
                    'color' => [
                        'blue' => ['500' => ['$type' => 'color', '$value' => '#3b82f6']],
                        'neutral' => [
                            '50' => ['$type' => 'color', '$value' => '#fafafa'],
                            '700' => ['$type' => 'color', '$value' => '#374151'],
                            '900' => ['$type' => 'color', '$value' => '#111827'],
                        ],
                    ],
                ],
                'semantic' => [
                    'color' => [
                        'brand' => ['$type' => 'color', '$value' => '{primitive.color.blue.500}'],
                        'text' => [
                            'body' => ['$type' => 'color', '$value' => '{primitive.color.neutral.700}'],
                            'heading' => ['$type' => 'color', '$value' => '{primitive.color.neutral.900}'],
                        ],
                    ],
                ],
            ],
            'modes' => ['light'],
            'schema_version' => '1.0.0',
            'is_system' => false,
        ]);

        $site->update(['active_theme_id' => $theme->id]);

        // Seed default grid presets for the new site
        $this->gridPresetSeeder->seed($site);

        // Create default category
        Category::create([
            'site_id' => $site->id,
            'name' => 'General',
            'slug' => 'general',
            'sort_order' => 0,
        ]);

        return $site->load('theme');
    }

    public function updateSite(Site $site, array $data): Site
    {
        // Merge settings rather than replace — prevents tabs from overwriting each other
        if (isset($data['settings']) && is_array($data['settings'])) {
            $existing = $site->settings ?? [];
            $incoming = $data['settings'];
            $data['settings'] = array_merge($existing, $incoming);
        }

        $site->update($data);

        return $site->fresh(['theme']);
    }

    public function deleteSite(Site $site): void
    {
        $site->delete();
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 1;

        while (Site::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }
}
