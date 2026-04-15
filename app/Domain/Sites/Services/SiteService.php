<?php

namespace App\Domain\Sites\Services;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\Theme;
use Illuminate\Support\Str;

class SiteService
{
    public function createSite(array $data, Tenant $tenant): Site
    {
        $data['tenant_id'] = $tenant->id;
        $data['slug'] = $data['slug'] ?? $this->generateUniqueSlug($data['name']);

        $site = Site::create($data);

        $theme = Theme::create([
            'site_id' => $site->id,
            'name' => 'Default',
            'config' => [
                'colors' => ['primary' => '#3b82f6', 'secondary' => '#64748b'],
                'fonts' => ['heading' => 'Inter', 'body' => 'Inter'],
            ],
            'template_path' => 'themes/default',
            'is_system' => false,
        ]);

        $site->update(['active_theme_id' => $theme->id]);

        return $site->load('theme');
    }

    public function updateSite(Site $site, array $data): Site
    {
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
