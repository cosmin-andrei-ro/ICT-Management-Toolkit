<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Risk\Application\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\User;
use Modules\Risk\Application\Notifications\RiskUpdate;
use Modules\Risk\Domain\Enums\AcceptanceStatus;
use Modules\Risk\Domain\Models\Acceptance;
use Modules\Risk\Domain\Models\Risk;
use Modules\Risk\Domain\Models\RiskSettings;

/**
 * Acceptance of a risk's residual level (owner's decision): below the
 * organization's threshold the owner (or a risk manager) accepts it at once;
 * from the threshold a risk manager approves it, never the owner nor the
 * person who asked. Valid until the next reassessment; a different residual
 * risk voids it. Audited on the risk.
 */
final readonly class AcceptRisk
{
    public function __construct(
        private ConnectionInterface $db,
        private AuditTrail $trail,
    ) {}

    /**
     * @return Acceptance Approved at once, or pending.
     *
     * @throws InvalidArgumentException without a residual risk or a justification, or with a request already pending
     */
    public function request(Risk $risk, string $justification, User $actor): Acceptance
    {
        $justification = trim($justification);

        $acceptance = $this->db->transaction(function () use ($risk, $justification, $actor): Acceptance {
            $locked = Risk::query()->lockForUpdate()->findOrFail($risk->id);

            if ($locked->residual_likelihood === null || $locked->residual_impact === null || $justification === '') {
                throw new InvalidArgumentException('Assess the residual risk and say why it is acceptable.');
            }

            if (Acceptance::query()->where('risk_id', $locked->id)->where('status', AcceptanceStatus::Pending)->exists()) {
                throw new InvalidArgumentException('An acceptance already waits for approval.');
            }

            $settings = RiskSettings::current();
            $atOnce = (int) $locked->residualScore() < $settings->approval_from;

            $acceptance = new Acceptance;
            $acceptance->forceFill([
                'organization_id' => $locked->organization_id,
                'risk_id' => $locked->id,
                'likelihood' => $locked->residual_likelihood,
                'impact' => $locked->residual_impact,
                'justification' => mb_substr($justification, 0, 5000),
                'status' => $atOnce ? AcceptanceStatus::Approved : AcceptanceStatus::Pending,
                'requested_by' => $actor->id,
                'requested_at' => now(),
                'decided_by' => $atOnce ? $actor->id : null,
                'decided_at' => $atOnce ? now() : null,
            ]);

            if ($atOnce) {
                $this->approveInto($acceptance, $locked, $settings);
            } else {
                $acceptance->save();
            }

            $this->trail->record(AuditEvent::Updated, $locked, null, ['acceptance' => $acceptance->status->value.' at '.$acceptance->score()], $actor->id);

            return $acceptance;
        });

        if ($acceptance->status === AcceptanceStatus::Pending) {
            $notification = new RiskUpdate('acceptance_requested', $risk->code().' '.$risk->title, route('risk.risks.show', $risk), (string) $acceptance->score());

            User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($risk->organization_id))->get()
                ->filter(fn (User $user): bool => $user->canSignIn() && $this->mayDecide($user, $acceptance, $risk))
                ->each(fn (User $user) => $user->notify($notification));
        }

        $risk->refresh();

        return $acceptance;
    }

    /**
     * @throws AuthorizationException when the user may not decide this acceptance
     * @throws InvalidArgumentException when it no longer waits, or a rejection has no reason
     */
    public function decide(Acceptance $acceptance, bool $approve, ?string $comment, User $actor): void
    {
        $comment = $comment !== null ? trim($comment) : '';

        if (! $approve && $comment === '') {
            throw new InvalidArgumentException('Say why the acceptance is rejected.');
        }

        $risk = $this->db->transaction(function () use ($acceptance, $approve, $comment, $actor): Risk {
            $locked = Acceptance::query()->lockForUpdate()->findOrFail($acceptance->id);
            $risk = Risk::query()->lockForUpdate()->findOrFail($locked->risk_id);

            if ($locked->status !== AcceptanceStatus::Pending) {
                throw new InvalidArgumentException('The acceptance no longer waits for a decision.');
            }

            if (! $this->mayDecide($actor, $locked, $risk)) {
                throw new AuthorizationException;
            }

            $locked->forceFill(['decided_by' => $actor->id, 'decided_at' => now(), 'comment' => $comment !== '' ? mb_substr($comment, 0, 2000) : null]);

            if ($approve) {
                $locked->status = AcceptanceStatus::Approved;
                $this->approveInto($locked, $risk, RiskSettings::current());
            } else {
                $locked->forceFill(['status' => AcceptanceStatus::Rejected])->save();
            }

            $this->trail->record(AuditEvent::Updated, $risk, null, ['acceptance' => $locked->status->value], $actor->id);

            return $risk;
        });

        $notification = new RiskUpdate($approve ? 'acceptance_approved' : 'acceptance_rejected', $risk->code().' '.$risk->title, route('risk.risks.show', $risk), $comment !== '' ? $comment : null);

        User::query()->whereKey(array_values(array_filter([$risk->owner_id, $acceptance->requested_by])))->get()
            ->filter(fn (User $user): bool => $user->canSignIn() && $user->id !== $actor->id)
            ->each(fn (User $user) => $user->notify($notification));
    }

    /**
     * Risk managers decide acceptances from the threshold; never the risk's
     * owner nor the person who asked.
     */
    public function mayDecide(User $user, Acceptance $acceptance, Risk $risk): bool
    {
        return $acceptance->status === AcceptanceStatus::Pending
            && $user->can('risk.acceptance.approve')
            && $user->id !== $risk->owner_id
            && $user->id !== $acceptance->requested_by;
    }

    private function approveInto(Acceptance $acceptance, Risk $risk, RiskSettings $settings): void
    {
        $until = ($risk->next_review_on !== null && $risk->next_review_on->isFuture())
            ? $risk->next_review_on
            : now()->addMonthsNoOverflow($settings->review_months)->toImmutable()->startOfDay();

        $acceptance->forceFill(['valid_until' => $until])->save();
        $risk->forceFill(['accepted_until' => $until])->save();
    }
}
