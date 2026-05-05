<?php
namespace Database\Factories;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'original_name' => fake()->word() . '.jpg',
            'storage_path' => 'sites/test/assets/' . fake()->uuid() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(1000, 5000000),
            'checksum' => hash('sha256', fake()->uuid()),
            'variants' => [],
        ];
    }
}
