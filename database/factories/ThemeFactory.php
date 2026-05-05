<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => 'Default',
            'config' => ['colors' => ['primary' => '#3b82f6']],
            'template_path' => 'themes/default',
            'is_system' => false,
        ];
    }
}
