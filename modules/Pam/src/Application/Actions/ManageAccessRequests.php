<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\Notifications\AccessUpdate;
use Modules\Pam\Application\Policies\AccessRequestPolicy;
use Modules\Pam\Domain\Enums\RequestStatus;
use Modules\Pam\Domain\Models\AccessRequest;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * Asking for a credential that needs approval, and deciding on it. The caller
 * checked the policy and, for a decision, the second factor. The row lock keeps
 * two people from deciding the same request.
 */
final readonly class ManageAccessRequests
{
    public function __construct(private ConnectionInterface $db) {}

    public function ask(PrivilegedAccount $account, User $requester, string $reason): AccessRequest
    {
        $waiting = AccessRequest::query()->where('account_id', $account->id)->where('user_id', $requester->id)->where('status', RequestStatus::Pending)->first();

        if ($waiting !== null) {
            return $waiting;
        }

        $request = new AccessRequest;
        $request->forceFill([
            'account_id' => $account->id,
            'user_id' => $requester->id,
            'status' => RequestStatus::Pending,
            'reason' => mb_substr(trim($reason), 0, 500),
        ])->save();
        Notification::send($this->deciders($account, $requester), new AccessUpdate('request_waiting', $account->name, route('pam.requests')));

        return $request;
    }

    /**
     * @throws InvalidArgumentException When it was decided in the meantime.
     */
    public function decide(AccessRequest $request, User $decider, bool $approved, string $comment): void
    {
        $this->db->transaction(function () use ($request, $decider, $approved, $comment): void {
            $current = AccessRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== RequestStatus::Pending || $current->user_id === $decider->id) {
                throw new InvalidArgumentException('This request no longer waits for a decision.');
            }

            $request->forceFill([
                'status' => $approved ? RequestStatus::Approved : RequestStatus::Rejected,
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'decision_comment' => trim($comment) !== '' ? mb_substr(trim($comment), 0, 500) : null,
                'expires_at' => $approved ? now()->addMinutes($request->account->access_minutes) : null,
            ])->save();
        });

        $request->user->notify(new AccessUpdate($approved ? 'request_approved' : 'request_rejected', $request->account->name, route('pam.accounts.show', $request->account_id)));
    }

    /**
     * Ends what someone asked for or was approved for, when their access goes:
     * giving it back later must not bring back an old approval.
     */
    public function endFor(string $userId, ?string $accountId = null): void
    {
        $requests = AccessRequest::query()->where('user_id', $userId)->when($accountId !== null, fn (Builder $query) => $query->where('account_id', $accountId));

        $requests->clone()->where('status', RequestStatus::Pending)->get()->each->delete();
        $requests->clone()->where('status', RequestStatus::Approved)->where('expires_at', '>', now())->get()
            ->each(fn (AccessRequest $request): bool => $request->forceFill(['expires_at' => now()])->save());
    }

    /**
     * @return Collection<int, User>
     */
    private function deciders(PrivilegedAccount $account, User $requester): Collection
    {
        $mayDecide = fn (User $user): bool => $user->id !== $requester->id && AccessRequestPolicy::mayDecideFor($user, $account);
        $named = User::query()->whereKey(array_filter([$account->approver_id, $account->owner_id]))->where('is_active', true)->get()->toBase()->filter($mayDecide);

        if ($named->isNotEmpty()) {
            return $named->values();
        }

        return User::query()->where('is_active', true)
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey($account->organization_id))
            ->get()->toBase()->filter(fn (User $user): bool => $mayDecide($user) && $user->can('pam.account.manage'))->values();
    }
}
