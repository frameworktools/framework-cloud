<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'project_type' => 'project',
            'description' => $this->faker->optional()->sentence(),
            'payload' => [],
        ];
    }

    public function bare(): static
    {
        return $this->state(fn () => ['project_type' => 'bare']);
    }
}
