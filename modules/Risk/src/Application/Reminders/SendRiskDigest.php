<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Reminders;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Risk\Application\Notifications\RiskDigest;
use Modules\Risk\Domain\Enums\AcceptanceStatus;
use Modules\Risk\Domain\Enums\SuggestionStatus;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskAction;
use Modules\Risk\Domain\Models\Suggestion;

/**
 * Weekly, per organization: what risk managers should chase. Nothing is sent
 * when there is nothing to chase.
 */
final readonly class SendRiskDigest
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @return array{suggestions: int, pending_acceptances: int, acceptances_expiring: int, overdue_actions: int, reviews_overdue: int}
     */
    public function figures(CarbonImmutable $today): array
    {
        return [
            'suggestions' => Suggestion::query()->where('status', SuggestionStatus::Open)->count(),
            'pending_acceptances' => Acceptance::query()->where('status', AcceptanceStatus::Pending)->count(),
            'acceptances_expiring' => Risk::query()->where('status', 'open')->whereBetween('accepted_until', [$today->toDateString(), $today->addDays(30)->toDateString()])->count(),
            'overdue_actions' => RiskAction::query()->whereIn('status', ['planned', 'in_progress'])->whereNotNull('due_on')->where('due_on', '<', $today->toDateString())->count(),
            'reviews_overdue' => Risk::query()->where('status', 'open')->whereNotNull('next_review_on')->where('next_review_on', '<', $today->toDateString())->count(),
        ];
    }

    /**
     * @return int Organizations whose risk managers were emailed.
     */
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $sent = 0;

        foreach (Organization::query()->orderBy('id')->get(['id']) as $organization) {
            $this->context->runAs($organization->id, function () use ($today, &$sent): void {
                $figures = array_filter($this->figures($today), fn (int $count): bool => $count > 0);

                if ($figures === []) {
                    return;
                }

                $notification = new RiskDigest($figures, route('risk.index'));
                $recipients = User::query()->where('is_active', true)->whereHas('roles')->get()
                    ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('risk.risk.manage'));

                $recipients->each(fn (User $user) => $user->notify($notification));
                $sent += $recipients->isNotEmpty() ? 1 : 0;
            });
        }

        return $sent;
    }
}
