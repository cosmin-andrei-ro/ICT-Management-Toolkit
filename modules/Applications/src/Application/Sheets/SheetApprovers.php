<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Sheets;

use Modules\Applications\Domain\Enums\ApprovalRole;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Core\Domain\Models\User;

/**
 * Who may give which approval of a submitted version (owner's decision): the
 * business owner, the technical owner (application managers may stand in for
 * either) and a security officer. Nobody approves a version they submitted,
 * and one person gives one approval.
 */
final class SheetApprovers
{
    /**
     * Approvals still missing that this user may give.
     *
     * @return list<ApprovalRole>
     */
    public function rolesFor(User $user, Sheet $sheet): array
    {
        if ($sheet->status !== SheetStatus::Submitted || $user->id === $sheet->submitted_by) {
            return [];
        }

        $given = $sheet->approvals()->get();

        if ($given->contains(fn ($approval): bool => $approval->user_id === $user->id)) {
            return [];
        }

        $done = $given->map(fn ($approval): ApprovalRole => $approval->role)->all();

        return array_values(array_filter(ApprovalRole::cases(), fn (ApprovalRole $role): bool => ! in_array($role, $done, true) && $this->holds($user, $sheet, $role)));
    }

    /**
     * Who should be asked for each role, for notifications.
     *
     * @return list<User>
     */
    public function candidates(Sheet $sheet): array
    {
        $application = $sheet->application;
        $people = User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($sheet->organization_id))->get();

        return array_values($people->filter(fn (User $user): bool => $user->canSignIn() && $user->id !== $sheet->submitted_by && (
            $user->id === $application->business_owner_id
            || $user->id === $application->technical_owner_id
            || $user->can('applications.sheet.approve_security')
        ))->all());
    }

    public function holds(User $user, Sheet $sheet, ApprovalRole $role): bool
    {
        $application = $sheet->application;

        return match ($role) {
            ApprovalRole::Business => $user->id === $application->business_owner_id || $user->can('applications.application.manage'),
            ApprovalRole::Technical => $user->id === $application->technical_owner_id || $user->can('applications.application.manage'),
            ApprovalRole::Security => $user->can('applications.sheet.approve_security'),
        };
    }
}
