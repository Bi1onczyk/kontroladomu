<?php

namespace Database\Factories;

use App\Domain\Accounts\Enums\UserRole;
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
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'terms_accepted_at' => now(),
            'terms_version' => config('legal.terms_version'),
            'privacy_accepted_at' => now(),
            'privacy_version' => config('legal.privacy_version'),
        ];
    }

    /**
     * Konto z daną rolą (rola jest tworzona, jeśli nie istnieje).
     */
    private function withRole(UserRole $role): static
    {
        return $this->afterCreating(function (User $user) use ($role) {
            UserRole::ensureExist();
            $user->assignRole($role->value);
        });
    }

    /** Właściciel nieruchomości. */
    public function owner(): static
    {
        return $this->withRole(UserRole::Owner);
    }

    /** Fachowiec (z NIP i nazwą firmy). */
    public function specialist(): static
    {
        return $this->state(fn () => [
            'company_name' => fake()->company(),
            'nip' => '6931171824',
        ])->withRole(UserRole::Specialist);
    }

    /** Pracownik fachowca. */
    public function technician(): static
    {
        return $this->withRole(UserRole::Technician);
    }

    /** Administrator serwisu. */
    public function admin(): static
    {
        return $this->withRole(UserRole::Admin);
    }

    /** Konto z włączonym i potwierdzonym 2FA (sekret testowy). */
    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['aaaaaaaaaa-bbbbbbbbbb', 'cccccccccc-dddddddddd'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /** Konto zablokowane. */
    public function banned(): static
    {
        return $this->state(fn () => ['banned_at' => now()]);
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
}
