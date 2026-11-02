<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Modules\Core\Application\Notifications\ContractDeadlineApproaching;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Daily, across organizations: renews contracts that renew by themselves, then
 * emails the owner and the contract managers before each deadline (end date
 * minus notice period), at the configured number of days before it. Each
 * reminder is sent once; when the job missed several, one email covers them.
 */
final readonly class SendContractReminders
{
    public function __construct(
        private OrganizationContext $context,
        private ConnectionInterface $db,
    ) {}

    /**
     * @return int Contracts someone was reminded about.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $this->renew($today);

        $reminderDays = Contract::reminderDays();

        if ($reminderDays === []) {
            return 0;
        }

        $day = $today->toDateString();
        $reminded = 0;
        /** @var array<string, list<User>> $managers */
        $managers = [];

        Contract::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('end_date')
            ->where('end_date', '>=', $day)
            ->where(fn ($query) => $query->whereNull('terminated_on')->orWhere('terminated_on', '>', $day))
            ->whereRaw('end_date - COALESCE(notice_days, 0) - ?::integer <= ?::date', [max($reminderDays), $day])
            ->orderBy('id')
            ->each(function (Contract $contract) use ($today, $reminderDays, &$reminded, &$managers): void {
                $this->context->runAs($contract->organization_id, function () use ($contract, $today, $reminderDays, &$reminded, &$managers): void {
                    if ($this->remind($contract, $today, $reminderDays, $managers)) {
                        $reminded++;
                    }
                });
            });

        return $reminded;
    }

    /**
     * @param  list<int>  $reminderDays
     * @param  array<string, list<User>>  $managers  Per organization, filled as needed.
     */
    private function remind(Contract $contract, CarbonImmutable $today, array $reminderDays, array &$managers): bool
    {
        $deadline = $contract->deadline();

        if ($deadline === null) {
            return false;
        }

        $rows = [];

        foreach ($reminderDays as $days) {
            if ($deadline->subDays($days) <= $today) {
                $rows[] = [
                    'id' => (string) Str::uuid7(),
                    'contract_id' => $contract->id,
                    'deadline' => $deadline->toDateString(),
                    'days_before' => $days,
                    'sent_at' => now(),
                ];
            }
        }

        // The unique index decides: a reminder already recorded is not sent again.
        if ($rows === [] || $this->db->table('core_contract_reminders')->insertOrIgnore($rows) === 0) {
            return false;
        }

        $contract->load(['supplier', 'owner']);
        $managers[$contract->organization_id] ??= $this->contractManagers();

        $notification = new ContractDeadlineApproaching(
            $contract->title,
            $contract->supplier->name,
            $contract->reference,
            $deadline->toDateString(),
            $contract->end_date?->toDateString() ?? '',
            $contract->auto_renews,
            route('core.contracts.show', $contract),
        );

        collect([$contract->owner, ...$managers[$contract->organization_id]])
            ->filter(fn (?User $user): bool => $user !== null && $user->canSignIn())
            ->unique('id')
            ->each(fn (User $user) => $user->notify($notification));

        return true;
    }

    /**
     * People of the current organization who manage contracts. Only people with a
     * role here are considered, not every employee.
     *
     * @return list<User>
     */
    private function contractManagers(): array
    {
        return array_values(User::query()
            ->where('is_active', true)
            ->whereHas('roles')
            ->get()
            ->filter(fn (User $user): bool => $user->can('core.contract.manage'))
            ->all());
    }

    /**
     * A contract that renews by itself and whose end passed gets its next term,
     * as many terms as needed to reach today.
     */
    private function renew(CarbonImmutable $today): void
    {
        Contract::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('auto_renews', true)
            ->whereNull('terminated_on')
            ->where('end_date', '<', $today->toDateString())
            ->orderBy('id')
            ->each(function (Contract $contract) use ($today): void {
                $this->context->runAs($contract->organization_id, function () use ($contract, $today): void {
                    $end = $contract->end_date;
                    $months = max(1, $contract->renewal_months ?? 12);

                    while ($end !== null && $end < $today) {
                        $end = $end->addMonthsNoOverflow($months);
                    }

                    $contract->forceFill(['end_date' => $end])->save();
                });
            });
    }
}
