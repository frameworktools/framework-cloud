<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Blueprint>
 */
class BlueprintFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'key' => Str::slug($name, '_'),
            'name' => $name,
            'description' => $this->faker->sentence(),
            'visibility' => 'private',
            'is_system' => false,
            'position' => 0,
        ];
    }
}
