<?php
namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'editor',
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => 'owner']);
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function editor(): static
    {
        return $this->state(['role' => 'editor']);
    }
}
