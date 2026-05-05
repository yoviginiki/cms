<?php
namespace Database\Factories;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(2),
            'status' => 'draft',
            'seo_meta' => [],
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()]);
    }
}
