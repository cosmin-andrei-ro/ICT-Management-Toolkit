<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Warranty;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;
use Modules\Itam\Application\Notifications\EndingSoon;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;

/**
 * Daily, across organizations: one email per organization to the people who
 * manage assets, listing warranties that end within the configured days and
 * were not announced yet. A new warranty end date is announced again.
 */
final readonly class SendWarrantyReminders
{
    /** Assets named in one email; the rest are counted. */
    private const int LISTED = 50;

    public function __construct(
        private OrganizationContext $context,
        private Config $config,
    ) {}

    /**
     * @return int Assets announced.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = $this->config->get('itam.assets.warranty_reminder_days');
        $days = is_int($days) && $days > 0 ? $days : 30;

        $due = Asset::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereNotIn('status', [AssetStatus::Retired->value, AssetStatus::Lost->value, AssetStatus::Disposed->value])
            ->whereBetween('warranty_ends_on', [$today->toDateString(), $today->addDays($days)->toDateString()])
            ->where(fn ($query) => $query->whereNull('warranty_reminded_for')->orWhereColumn('warranty_reminded_for', '!=', 'warranty_ends_on'))
            ->orderBy('warranty_ends_on')
            ->get()
            ->groupBy('organization_id');

        $announced = 0;

        foreach ($due as $organizationId => $assets) {
            $this->context->runAs((string) $organizationId, function () use ($assets, &$announced): void {
                $this->announce($assets);
                $announced += $assets->count();
            });
        }

        return $announced;
    }

    /**
     * @param  Collection<int, Asset>  $assets
     */
    private function announce(Collection $assets): void
    {
        $assets->load('category');
        $lines = array_values($assets->take(self::LISTED)
            ->map(fn (Asset $asset): string => "{$asset->asset_tag} · {$asset->displayName()} · {$asset->warranty_ends_on?->toDateString()}")
            ->all());

        $notification = new EndingSoon('warranty', $lines, max(0, $assets->count() - self::LISTED), route('itam.assets.index', ['warranty' => 1]));

        User::query()
            ->where('is_active', true)
            ->whereHas('roles')
            ->get()
            ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('itam.asset.manage'))
            ->each(fn (User $user) => $user->notify($notification));

        // Bookkeeping, not a change of the asset: kept out of the audit trail on purpose.
        Asset::query()->whereKey($assets->modelKeys())->update(['warranty_reminded_for' => new Expression('warranty_ends_on')]);
    }
}
