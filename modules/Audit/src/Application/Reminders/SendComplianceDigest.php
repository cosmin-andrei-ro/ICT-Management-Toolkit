<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Reminders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Audit\Application\Notifications\ComplianceDigest;
use Modules\Audit\Domain\Enums\FindingSeverity;
use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Evidence;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Weekly, per organization: what compliance managers should chase. Nothing
 * is sent when there is nothing to chase.
 */
final readonly class SendComplianceDigest
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @return array{findings_to_verify: int, major_open: int, findings_overdue: int, tests_overdue: int, evidence_expired: int, requirements_not_covered: int}
     */
    public function figures(CarbonImmutable $today): array
    {
        $open = fn () => Finding::query()->where('status', '!=', FindingStatus::Closed->value);

        return [
            'findings_to_verify' => Finding::query()->where('status', FindingStatus::Verification->value)->count(),
            'major_open' => $open()->where('severity', FindingSeverity::MajorNonconformity->value)->count(),
            'findings_overdue' => $open()->whereNotNull('due_on')->where('due_on', '<', $today->toDateString())->count(),
            'tests_overdue' => Control::query()->where('status', '!=', 'retired')->whereNotNull('next_test_on')->where('next_test_on', '<', $today->toDateString())->count(),
            'evidence_expired' => Evidence::query()->whereNotNull('valid_until')->where('valid_until', '<', $today->toDateString())->where('valid_until', '>=', $today->subDays(7)->toDateString())->count(),
            'requirements_not_covered' => self::uncovered()->count(),
        ];
    }

    /**
     * Applicable requirements of active frameworks that no control (other than retired) meets.
     *
     * @return Builder<Requirement>
     */
    public static function uncovered(): Builder
    {
        return Requirement::query()
            ->where('is_applicable', true)
            ->whereRelation('framework', 'is_active', true)
            ->whereDoesntHave('controls', fn ($controls) => $controls->where('status', '!=', 'retired'));
    }

    /**
     * @return int Organizations whose compliance managers were emailed.
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

                $notification = new ComplianceDigest($figures, route('audit.index'));
                $recipients = User::query()->where('is_active', true)->whereHas('roles')->get()
                    ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('audit.compliance.manage'));

                $recipients->each(fn (User $user) => $user->notify($notification));
                $sent += $recipients->isNotEmpty() ? 1 : 0;
            });
        }

        return $sent;
    }
}
