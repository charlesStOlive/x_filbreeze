<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
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

    /**
     * Les permissions données directement à l'utilisateur, créées si elles n'existent pas encore (une base de test est vide).
     */
    public function withPermissions(string ...$permissions): static
    {
        return $this->afterCreating(function (\App\Models\User $user) use ($permissions): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach ($permissions as $name) {
                $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
            }
        });
    }

    /** Comme le rôle crm_manager du seeder : le panel, tout le CRM, et voir les utilisateurs. */
    public function crmManager(): static
    {
        return $this->withPermissions('panels.admin.access', 'crm.*', 'administration.user.viewany', 'administration.user.view');
    }
}
