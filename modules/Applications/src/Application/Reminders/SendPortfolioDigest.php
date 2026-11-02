<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Reminders;

use Carbon\CarbonImmutable;
use Modules\Applications\Application\Notifications\PortfolioDigest;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Weekly, per organization: what application managers should chase.
 * Nothing is sent when there is nothing to chase.
 */
final readonly class SendPortfolioDigest
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @return array{not_documented: int, review_overdue: int, awaiting_approval: int}
     */
    public function figures(CarbonImmutable $today): array
    {
        return [
            'not_documented' => Application::query()->whereNull('approved_at')->count(),
            'review_overdue' => Application::query()->whereNotNull('next_review_on')->where('next_review_on', '<', $today->toDateString())->count(),
            'awaiting_approval' => Sheet::query()->where('status', 'submitted')->count(),
        ];
    }

    /**
     * @return int Organizations whose managers were emailed.
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

                $notification = new PortfolioDigest($figures, route('applications.index', ['due' => 1]));
                $recipients = User::query()->where('is_active', true)->whereHas('roles')->get()
                    ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('applications.application.manage'));

                $recipients->each(fn (User $user) => $user->notify($notification));
                $sent += $recipients->isNotEmpty() ? 1 : 0;
            });
        }

        return $sent;
    }
}
