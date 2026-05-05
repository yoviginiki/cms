<?php
namespace Database\Factories;

use App\Models\Post;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(2),
            'excerpt' => fake()->paragraph(),
            'status' => 'draft',
            'seo_meta' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()]);
    }
}
