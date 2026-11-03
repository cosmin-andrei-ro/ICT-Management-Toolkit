<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Authentication;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Domain\Models\SecuritySettings;
use Modules\Core\Domain\Models\User;

/**
 * What a person's new password must be (ADR 0069). A password that is the only
 * factor must be longer, as NIST SP 800-63B-4 asks. Composition rules apply only
 * when one of the person's organizations asks for them: the account is shared,
 * so its strictest organization decides.
 */
final readonly class PasswordPolicy
{
    public const int MIN_WITH_SECOND_FACTOR = 12;

    public const int MIN_AS_ONLY_FACTOR = 15;

    public const int MAX = 128;

    public function __construct(
        private TwoFactorAuthentication $twoFactor,
        private Config $config,
    ) {}

    public function minimumFor(User $user): int
    {
        return $this->twoFactor->isEnabled($user) || $this->twoFactor->isRequiredFor($user)
            ? self::MIN_WITH_SECOND_FACTOR
            : self::MIN_AS_ONLY_FACTOR;
    }

    public function needsCompositionFor(User $user): bool
    {
        return SecuritySettings::query()->withoutGlobalScopes()
            ->whereIn('organization_id', $user->organizations()->select('core_organizations.id'))
            ->where('password_composition_required', true)
            ->exists();
    }

    public function ruleFor(User $user): Password
    {
        $rule = self::base($this->minimumFor($user), $this->config->get('core.authentication.check_breached_passwords') === true);

        return $this->needsCompositionFor($user) ? $rule->mixedCase()->numbers()->symbols() : $rule;
    }

    /**
     * What to tell the person on the page where they choose it.
     */
    public function hintFor(User $user): string
    {
        $hint = __('core::security.password.rules', ['min' => $this->minimumFor($user)]);

        return $this->needsCompositionFor($user) ? $hint.' '.__('core::security.password.rules_composition') : $hint;
    }

    public static function base(int $minimum, bool $checkBreaches): Password
    {
        $rule = Password::min($minimum)->max(self::MAX);

        return $checkBreaches ? $rule->uncompromised() : $rule;
    }
}
