<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Audit\Application\Notifications\FindingUpdate;
use Modules\Audit\Domain\Enums\FindingStatus;
use Modules\Audit\Domain\Models\Finding;
use Modules\Core\Domain\Models\User;

/**
 * The end of a finding (ISO/IEC 27001 10.2): its owner records the root
 * cause of a nonconformity, completes the corrective actions and submits it;
 * a compliance manager other than the owner and the submitter verifies that
 * it is effective and closes it, or reopens it saying why.
 */
final readonly class CloseFinding
{
    public function __construct(private ConnectionInterface $db) {}

    public function rootCause(Finding $finding, ?string $rootCause): void
    {
        $rootCause = $rootCause !== null ? trim($rootCause) : '';
        $finding->forceFill(['root_cause' => $rootCause !== '' ? $rootCause : null])->save();
    }

    /**
     * @throws InvalidArgumentException when a nonconformity has no root cause or no corrective action, or an action is still open
     */
    public function submit(Finding $finding, User $actor): void
    {
        $this->db->transaction(function () use ($finding, $actor): void {
            $locked = Finding::query()->lockForUpdate()->findOrFail($finding->id);
            $actions = $locked->actions()->get();

            if ($locked->status !== FindingStatus::Open
                || ($locked->severity->isNonconformity() && ($locked->root_cause === null || $actions->isEmpty()))
                || $actions->contains(fn ($action): bool => $action->status->isOpen())) {
                throw new InvalidArgumentException('Not ready for verification.');
            }

            $locked->forceFill(['status' => FindingStatus::Verification, 'submitted_by' => $actor->id, 'submitted_at' => now()])->save();
        });

        $finding->refresh();
        $notification = new FindingUpdate('submitted', $finding->code().' '.$finding->title, route('audit.findings.show', $finding));

        User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($finding->organization_id))->get()
            ->filter(fn (User $user): bool => $user->canSignIn() && $user->can('verify', $finding))
            ->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * @throws InvalidArgumentException when reopening without saying why
     */
    public function verify(Finding $finding, bool $effective, ?string $notes, User $actor): void
    {
        $notes = $notes !== null ? trim($notes) : '';

        if (! $effective && $notes === '') {
            throw new InvalidArgumentException('Say why the finding is reopened.');
        }

        $this->db->transaction(function () use ($finding, $effective, $notes, $actor): void {
            $locked = Finding::query()->lockForUpdate()->findOrFail($finding->id);

            if ($locked->status !== FindingStatus::Verification) {
                throw new InvalidArgumentException('Not waiting for verification.');
            }

            $locked->forceFill($effective
                ? ['status' => FindingStatus::Closed, 'verified_by' => $actor->id, 'closed_at' => now(), 'verification_notes' => $notes !== '' ? $notes : null]
                : ['status' => FindingStatus::Open, 'submitted_by' => null, 'submitted_at' => null, 'verification_notes' => $notes])->save();
        });

        $finding->refresh();
        $owner = $finding->owner_id !== null ? User::query()->find($finding->owner_id) : null;

        if ($owner !== null && $owner->canSignIn()) {
            $owner->notify(new FindingUpdate($effective ? 'closed' : 'reopened', $finding->code().' '.$finding->title, route('audit.findings.show', $finding), $notes !== '' ? $notes : null));
        }
    }
}
