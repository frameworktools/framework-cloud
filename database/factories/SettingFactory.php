<?php

namespace Database\Factories;

use App\Models\Setting;
use App\Models\User;
use App\Support\DefaultSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_type' => (new User)->getMorphClass(),
            'owner_id' => User::factory(),
            'kind' => 'global',
            'document' => DefaultSettings::document(),
            'schema_version' => DefaultSettings::SCHEMA_VERSION,
            'revision' => 0,
        ];
    }
}
