<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Application\Actions;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Reporting\Domain\Models\ReportSubscription;

/**
 * Who receives the monthly report: active members of the organization who
 * may see the management overview, chosen by an administrator.
 */
final readonly class ManageReportSubscriptions
{
    public const string VIEW = 'reporting.management.view';

    public const string MANAGE = 'reporting.subscriptions.manage';

    public function __construct(private OrganizationContext $context) {}

    /**
     * People who may be subscribed and are not yet.
     *
     * @return Collection<int, User>
     */
    public function candidates(): Collection
    {
        return User::query()->where('is_active', true)
            ->whereHas('organizations', fn ($query) => $query->whereKey($this->context->requireId()))
            ->permission(self::VIEW)
            ->whereNotIn('id', ReportSubscription::query()->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function subscribe(User $actor, string $userId): ReportSubscription
    {
        $this->authorize($actor);

        if (! $this->candidates()->contains('id', $userId)) {
            throw ValidationException::withMessages(['subscriber' => __('reporting::management.subscriptions.not_eligible')]);
        }

        $subscription = new ReportSubscription;
        $subscription->forceFill(['user_id' => $userId, 'created_by' => $actor->id])->save();

        return $subscription;
    }

    public function unsubscribe(User $actor, string $subscriptionId): void
    {
        $this->authorize($actor);
        ReportSubscription::query()->findOrFail($subscriptionId)->delete();
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->can(self::MANAGE), 403);
    }
}
