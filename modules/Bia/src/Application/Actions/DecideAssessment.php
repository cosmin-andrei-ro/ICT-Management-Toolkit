<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Bia\Application\Notifications\AssessmentUpdate;
use Modules\Bia\Domain\Enums\AssessmentStatus;
use Modules\Bia\Domain\Enums\Tier;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BiaSettings;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Models\User;

/**
 * Approves a submitted assessment, or returns it to the owner with a reason.
 * Approval supersedes the version in force and copies the recovery objectives
 * and the next review date onto the process.
 */
final readonly class DecideAssessment
{
    public function __construct(
        private ConnectionInterface $db,
        private Gate $gate,
    ) {}

    /**
     * @throws AuthorizationException for the owner, the submitter, or someone without the right
     * @throws ValidationException keyed "comment" when returning without a reason
     */
    public function handle(Assessment $assessment, User $actor, bool $approve, ?string $comment): Assessment
    {
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        if (! $approve && $comment === null) {
            throw ValidationException::withMessages(['comment' => __('bia::assessments.errors.return_reason')]);
        }

        $assessment = $this->db->transaction(function () use ($assessment, $actor, $approve, $comment): Assessment {
            $assessment = Assessment::query()->with('process')->lockForUpdate()->findOrFail($assessment->id);

            // Checked again under the lock: the status may have changed since the page was shown.
            if (! $this->gate->forUser($actor)->allows('decide', $assessment)) {
                throw new AuthorizationException;
            }

            if (! $approve) {
                $assessment->forceFill(['status' => AssessmentStatus::Returned, 'decided_at' => now(), 'decided_by' => $actor->id, 'decision_comment' => $comment])->save();

                return $assessment;
            }

            Assessment::query()->where('process_id', $assessment->process_id)->where('status', AssessmentStatus::Approved->value)
                ->get()->each(fn (Assessment $previous) => $previous->forceFill(['status' => AssessmentStatus::Superseded])->save());

            $settings = BiaSettings::current();
            $assessment->forceFill([
                'status' => AssessmentStatus::Approved,
                'unacceptable_level' => $settings->unacceptable_level,
                'decided_at' => now(),
                'decided_by' => $actor->id,
                'decision_comment' => $comment,
            ])->save();

            $process = BusinessProcess::query()->findOrFail($assessment->process_id);
            $process->forceFill([
                'mtpd_minutes' => $assessment->mtpd_minutes,
                'rto_minutes' => $assessment->rto_minutes,
                'rpo_minutes' => $assessment->rpo_minutes,
                'tier' => $assessment->rto_minutes !== null ? Tier::fromRto($assessment->rto_minutes) : null,
                'approved_at' => now(),
                'next_review_on' => now()->addMonthsNoOverflow($settings->review_months)->toDateString(),
            ])->save();

            return $assessment;
        });

        $notification = new AssessmentUpdate($approve ? 'approved' : 'returned', $assessment->process->name, $assessment->version, route('bia.assessments.show', $assessment), $comment);

        collect([$assessment->process->owner, $assessment->process->deputy, $assessment->submitter])
            ->filter(fn (?User $user): bool => $user !== null && $user->canSignIn() && $user->id !== $actor->id)
            ->unique('id')
            ->each(fn (User $user) => $user->notify($notification));

        return $assessment;
    }
}
