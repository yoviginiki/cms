<?php
namespace Database\Factories;

use App\Models\Block;
use Illuminate\Database\Eloquent\Factories\Factory;

class BlockFactory extends Factory
{
    protected $model = Block::class;

    public function definition(): array
    {
        return [
            'blockable_id' => fake()->uuid(),
            'blockable_type' => 'page',
            'type' => 'text',
            'data' => ['content' => '<p>' . fake()->paragraph() . '</p>'],
            'order' => 0,
        ];
    }

    public function hero(): static
    {
        return $this->state([
            'type' => 'hero',
            'data' => ['title' => fake()->sentence(), 'subtitle' => fake()->sentence()],
        ]);
    }

    public function heading(): static
    {
        return $this->state([
            'type' => 'heading',
            'data' => ['text' => fake()->sentence(3), 'level' => 'h2'],
        ]);
    }
}
