<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Application\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Applications\Application\Notifications\SheetUpdate;
use Modules\Applications\Application\Sheets\SheetApprovers;
use Modules\Applications\Domain\Enums\ApprovalRole;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Exceptions\SheetLocked;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Domain\Models\SheetApproval;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;

/**
 * Approvals of a submitted major version: each role once; the version comes
 * into force with the third. Any approver may instead return it with a
 * reason, which clears the approvals given. Eligibility is re-checked under
 * a row lock.
 */
final readonly class DecideSheet
{
    public function __construct(
        private ConnectionInterface $db,
        private SheetApprovers $approvers,
        private SheetInForce $inForce,
        private AuditTrail $trail,
    ) {}

    /**
     * @return bool True when this approval brought the version into force.
     *
     * @throws AuthorizationException when the user may not give this approval
     */
    public function approve(Sheet $sheet, ApprovalRole $role, ?string $comment, User $actor): bool
    {
        $inForce = $this->db->transaction(function () use ($sheet, $role, $comment, $actor): bool {
            $locked = $this->lock($sheet);

            if (! in_array($role, $this->approvers->rolesFor($actor, $locked), true)) {
                throw new AuthorizationException;
            }

            $approval = new SheetApproval;
            $approval->forceFill([
                'organization_id' => $locked->organization_id,
                'sheet_id' => $locked->id,
                'role' => $role,
                'user_id' => $actor->id,
                'comment' => self::blankToNull($comment),
                'approved_at' => now(),
            ])->save();
            $this->trail->record(AuditEvent::Updated, $locked, null, ['approved_as' => $role->value], $actor->id);

            if ($locked->approvals()->count() < count(ApprovalRole::cases())) {
                return false;
            }

            $this->inForce->handle($locked);

            return true;
        });

        if ($inForce) {
            $this->tellOwners($sheet->refresh(), new SheetUpdate('in_force', $sheet->application->name, route('applications.sheets.show', $sheet), $sheet->versionLabel()), $actor);
        }

        return $inForce;
    }

    /**
     * @throws AuthorizationException when the user may give no approval of this version
     * @throws ValidationException without a reason (key "comment")
     */
    public function sendBack(Sheet $sheet, string $comment, User $actor): void
    {
        if (trim($comment) === '') {
            throw ValidationException::withMessages(['comment' => __('applications::sheet.errors.reason')]);
        }

        $this->db->transaction(function () use ($sheet, $comment, $actor): void {
            $locked = $this->lock($sheet);

            if ($this->approvers->rolesFor($actor, $locked) === []) {
                throw new AuthorizationException;
            }

            $locked->approvals()->delete();
            $locked->forceFill(['status' => SheetStatus::Returned, 'returned_at' => now(), 'returned_by' => $actor->id, 'return_comment' => mb_substr(trim($comment), 0, 5000)])->save();
        });

        $sheet->refresh();
        $this->tellOwners($sheet, new SheetUpdate('returned', $sheet->application->name, route('applications.sheets.show', $sheet), comment: $sheet->return_comment), $actor);
    }

    private function lock(Sheet $sheet): Sheet
    {
        $locked = Sheet::query()->with('application')->lockForUpdate()->findOrFail($sheet->id);

        if ($locked->status !== SheetStatus::Submitted) {
            throw new SheetLocked;
        }

        return $locked;
    }

    private function tellOwners(Sheet $sheet, SheetUpdate $notification, User $actor): void
    {
        $application = Application::query()->findOrFail($sheet->application_id);

        User::query()->whereKey(array_values(array_filter([$application->business_owner_id, $application->technical_owner_id, $application->deputy_id, $sheet->submitted_by])))->get()
            ->filter(fn (User $user): bool => $user->canSignIn() && $user->id !== $actor->id)
            ->each(fn (User $user) => $user->notify($notification));
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : mb_substr((string) $value, 0, 2000);
    }
}
