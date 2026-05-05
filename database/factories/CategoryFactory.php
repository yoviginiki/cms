<?php
namespace Database\Factories;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->word(),
            'slug' => fake()->unique()->slug(1),
            'sort_order' => 0,
        ];
    }
}
