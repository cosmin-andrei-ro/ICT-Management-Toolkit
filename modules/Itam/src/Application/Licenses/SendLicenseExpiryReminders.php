<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Licenses;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Itam\Application\Notifications\EndingSoon;
use Modules\Itam\Domain\Models\License;

/**
 * Daily, across organizations: one email per organization to the people who
 * manage licences, listing licences that expire within the configured days and
 * were not announced yet. A new expiry date is announced again.
 */
final readonly class SendLicenseExpiryReminders
{
    private const int LISTED = 50;

    public function __construct(
        private OrganizationContext $context,
        private Config $config,
    ) {}

    /**
     * @return int Licences announced.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = $this->config->get('itam.assets.license_reminder_days');
        $days = is_int($days) && $days > 0 ? $days : 60;

        $due = License::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereBetween('expires_on', [$today->toDateString(), $today->addDays($days)->toDateString()])
            ->where(fn ($query) => $query->whereNull('expiry_reminded_for')->orWhereColumn('expiry_reminded_for', '!=', 'expires_on'))
            ->orderBy('expires_on')
            ->get()
            ->groupBy('organization_id');

        $announced = 0;

        foreach ($due as $organizationId => $licenses) {
            $this->context->runAs((string) $organizationId, function () use ($licenses, &$announced): void {
                $this->announce($licenses);
                $announced += $licenses->count();
            });
        }

        return $announced;
    }

    /**
     * @param  Collection<int, License>  $licenses
     */
    private function announce(Collection $licenses): void
    {
        $licenses->load('product');
        $lines = array_values($licenses->take(self::LISTED)
            ->map(fn (License $license): string => "{$license->product->name} · {$license->name} · {$license->expires_on?->toDateString()}")
            ->all());

        $notification = new EndingSoon('license', $lines, max(0, $licenses->count() - self::LISTED), route('itam.licenses.index'));

        User::query()
            ->where('is_active', true)
            ->whereHas('roles')
            ->get()
            ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('itam.license.manage'))
            ->each(fn (User $user) => $user->notify($notification));

        // Bookkeeping, not a change of the licence: kept out of the audit trail on purpose.
        License::query()->whereKey($licenses->modelKeys())->update(['expiry_reminded_for' => new Expression('expires_on')]);
    }
}
