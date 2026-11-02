<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Reminders;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Query\Expression;
use Modules\Audit\Application\Notifications\ControlTestDue;
use Modules\Audit\Domain\Models\Control;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Tenancy\OrganizationScope;

/**
 * Daily, across organizations: emails the owner of each control due for its
 * periodic test, once per due date.
 */
final readonly class SendTestReminders
{
    public function __construct(
        private OrganizationContext $context,
        private Config $config,
    ) {}

    /**
     * @return int Controls announced.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $days = $this->config->get('audit.compliance.test_reminder_days');
        $horizon = $today->addDays(is_int($days) && $days > 0 ? $days : 14)->toDateString();
        $sent = 0;

        Control::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('status', '!=', 'retired')
            ->whereNotNull('owner_id')
            ->whereNotNull('next_test_on')
            ->where('next_test_on', '<=', $horizon)
            ->where(fn ($query) => $query->whereNull('test_reminded_for')->orWhereColumn('test_reminded_for', '!=', 'next_test_on'))
            ->orderBy('id')
            ->each(function (Control $control) use (&$sent): void {
                $this->context->runAs($control->organization_id, function () use ($control, &$sent): void {
                    $owner = User::query()->find($control->owner_id);

                    if ($owner !== null && $owner->canSignIn()) {
                        $owner->notify(new ControlTestDue($control->code().' '.$control->name, route('audit.controls.show', $control), (string) $control->next_test_on?->toDateString()));
                        $sent++;
                    }

                    // Bookkeeping, not a change of the control: kept out of the audit trail on purpose.
                    Control::query()->whereKey($control->id)->update(['test_reminded_for' => new Expression('next_test_on')]);
                });
            });

        return $sent;
    }
}
