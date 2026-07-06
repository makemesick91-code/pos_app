<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('08##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'tenant_id' => null,
            'store_id' => null,
            'role' => User::ROLE_CASHIER,
            'is_active' => true,
            'last_login_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function saasAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_SAAS_ADMIN,
            'tenant_id' => null,
            'store_id' => null,
        ]);
    }

    public function tenantOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_TENANT_OWNER,
        ]);
    }

    public function storeAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_STORE_ADMIN,
        ]);
    }

    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_CASHIER,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
