<?php
namespace Database\Factories;

use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(2),
            'status' => 'active',
            'seo_defaults' => ['title_template' => '{title} | {site_name}'],
            'settings' => [],
        ];
    }
}
