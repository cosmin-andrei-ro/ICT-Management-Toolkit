<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Authentication\ConfirmSecondFactor;
use Modules\Core\Domain\Models\User;
use SensitiveParameter;

/**
 * The second factor behind every sensitive step of the vault. A stolen session
 * could set up its own authenticator app and answer with it, so a new one must
 * wait, while the person, who was told by email, can react.
 */
final readonly class VaultSecondFactor
{
    public function __construct(
        private ConfirmSecondFactor $secondFactor,
        private Config $config,
    ) {}

    /**
     * @throws ValidationException keyed to $field
     */
    public function confirm(User $user, #[SensitiveParameter] string $code, string $purpose, string $field): void
    {
        $hours = $this->config->get('pam.vault.second_factor_settle_hours');
        $confirmedAt = $user->two_factor_confirmed_at;

        if (is_int($hours) && $hours > 0 && $confirmedAt !== null && $confirmedAt->copy()->addHours($hours)->isFuture()) {
            throw ValidationException::withMessages([$field => __('pam::accounts.errors.second_factor_too_new', ['hours' => $hours])]);
        }

        $this->secondFactor->confirm($user, $code, $purpose, $field);
    }
}
