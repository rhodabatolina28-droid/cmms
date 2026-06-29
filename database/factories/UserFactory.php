<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'user',
            'is_active' => true,
        ];
    }

    /**
     * email_verified_at is not used in this app (column was dropped in migration).
     * This state is kept for interface compatibility only and is a no-op.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
