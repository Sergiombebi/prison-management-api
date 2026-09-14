<?php

namespace Database\Factories;

use App\Enums\RoleUtilisateur;
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
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => RoleUtilisateur::Agent,
            'est_actif' => true,
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => RoleUtilisateur::Admin,
        ]);
    }

    /**
     * Indicate that the user is a doctor.
     */
    public function medecin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => RoleUtilisateur::Medecin,
        ]);
    }
}
