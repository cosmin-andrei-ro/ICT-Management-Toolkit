<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Domain\Directory\AuthSource;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    /**
     * An Active Directory account: no local password, identified by its GUID.
     */
    public function fromDirectory(?string $guid = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'auth_source' => AuthSource::Ldap,
            'password' => null,
            'directory_guid' => $guid ?? (string) Str::uuid(),
            'directory_username' => is_string($attributes['email']) ? Str::before($attributes['email'], '@') : null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Adds the user to the organization and makes it their current one.
     */
    public function memberOf(Organization $organization): static
    {
        return $this
            ->state(fn (): array => ['current_organization_id' => $organization->id])
            ->afterCreating(fn (User $user) => $user->organizations()->attach($organization));
    }
}
