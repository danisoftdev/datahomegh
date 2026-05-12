<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0'.fake()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => Role::factory(),
            'agent_id' => null,
            'shop_slug' => null,
            'shop_name' => null,
            'profile_picture' => null,
            'logo' => null,
            'whatsapp_number' => null,
            'whatsapp_channel' => null,
            'business_description' => null,
            'status' => 'active',
            'wallet_frozen' => false,
            'daily_order_limit' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function forAgent(User $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => $agent->id,
        ]);
    }
}
