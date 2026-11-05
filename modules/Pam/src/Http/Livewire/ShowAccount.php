<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Http\Livewire;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Pam\Application\Actions\ChangeSecret;
use Modules\Pam\Application\Actions\DeleteAccount;
use Modules\Pam\Application\Actions\GrantAccess;
use Modules\Pam\Application\Actions\ManageAccessRequests;
use Modules\Pam\Application\Actions\RevealSecret;
use Modules\Pam\Application\PamVault;
use Modules\Pam\Application\VaultSecondFactor;
use Modules\Pam\Domain\Exceptions\VaultNotConfigured;
use Modules\Pam\Domain\Models\AccessRequest;
use Modules\Pam\Domain\Models\AccountGrant;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * @property-read PrivilegedAccount $account
 */
#[Layout('core::components.layouts.app')]
final class ShowAccount extends Component
{
    #[Locked]
    public string $accountId = '';

    public string $code = '';

    public string $reason = '';

    public string $newSecret = '';

    public string $grantUserId = '';

    /** For the changes that need a fresh second factor: credential, access, deletion. */
    public string $actionCode = '';

    /**
     * For this response only. Not public, so it never enters Livewire's page state:
     * it is gone at the next request, and not sent back with it.
     */
    private ?string $shown = null;

    public function mount(PrivilegedAccount $account): void
    {
        $this->authorize('view', $account);
        $this->accountId = $account->id;
    }

    #[Computed]
    public function account(): PrivilegedAccount
    {
        return PrivilegedAccount::query()->with(['owner', 'grants.user'])->findOrFail($this->accountId);
    }

    public function reveal(RevealSecret $reveal): void
    {
        $this->authorize('reveal', $this->account);
        $this->validate([
            'reason' => [$this->account->requires_approval ? 'nullable' : 'required', 'string', 'max:500'],
            'code' => ['required', 'string', 'max:32'],
        ]);
        $code = $this->code;
        $this->reset('code');
        $user = $this->actor();

        try {
            $this->shown = $reveal->handle($this->account, $user, $code, $this->reason);
        } catch (VaultNotConfigured) {
            $this->addError('code', __('pam::accounts.errors.not_configured'));

            return;
        } catch (DecryptException) {
            // A wrong or missing key after a restore: nothing readable, and no stack trace with it.
            $this->addError('code', __('pam::accounts.errors.unreadable'));

            return;
        }

        $this->reset('reason');
    }

    public function askAccess(ManageAccessRequests $requests): void
    {
        $this->authorize('reveal', $this->account);
        abort_unless($this->account->requires_approval, 404);
        $this->validate(['reason' => ['required', 'string', 'max:500']]);
        $requests->ask($this->account, $this->actor(), $this->reason);
        $this->reset('reason');
    }

    /**
     * Someone confirms who has access is still right. Needs a code: it hides a
     * forgotten grant for another period.
     */
    public function confirmReview(VaultSecondFactor $secondFactor, AuditTrail $trail): void
    {
        $this->authorize('update', $this->account);
        $this->confirm($secondFactor, 'pam_review');
        $this->account->forceFill(['reviewed_at' => now(), 'reviewed_by' => $this->actor()->id, 'review_reminded_at' => null])->saveQuietly();
        $trail->record(AuditEvent::Updated, $this->account, null, ['access_reviewed' => $this->account->grants()->count()], $this->actor()->id);
        unset($this->account);
    }

    /**
     * Rendering again without the value is what hides it.
     */
    public function hide(): void {}

    public function changeSecret(ChangeSecret $change, VaultSecondFactor $secondFactor): void
    {
        $this->authorize('update', $this->account);
        $this->validate(['newSecret' => ['required', 'string', 'max:8192']]);
        $this->confirm($secondFactor, 'pam_change_secret');

        try {
            $change->handle($this->account, $this->newSecret, $this->actor());
        } catch (VaultNotConfigured) {
            $this->addError('newSecret', __('pam::accounts.errors.not_configured'));

            return;
        }

        $this->reset('newSecret');
        unset($this->account);
    }

    public function grant(GrantAccess $grant, VaultSecondFactor $secondFactor): void
    {
        $this->validate(['grantUserId' => ['required', 'uuid', Rule::in(array_keys($this->candidates()))]]);
        $person = User::query()->whereKey($this->grantUserId)->firstOrFail();
        $this->authorize('grantTo', [$this->account, $person]);

        $this->confirm($secondFactor, 'pam_grant');
        $grant->handle($this->account, $person, $this->actor());
        $this->reset('grantUserId');
        unset($this->account);
    }

    public function revoke(string $grantId, ManageAccessRequests $requests): void
    {
        $this->authorize('update', $this->account);
        $grant = AccountGrant::query()->where('account_id', $this->accountId)->whereKey($grantId)->firstOrFail();
        $grant->delete();
        $requests->endFor($grant->user_id, $this->accountId);
        unset($this->account);
    }

    public function delete(DeleteAccount $delete, VaultSecondFactor $secondFactor): void
    {
        $this->authorize('delete', $this->account);
        $this->confirm($secondFactor, 'pam_delete');
        $delete->handle($this->account);
        $this->redirectRoute('pam.accounts.index');
    }

    public function render(PamVault $vault, ConfigurationItems $items, Applications $applications): View
    {
        $account = $this->account;
        $this->authorize('view', $account);

        return view('pam::livewire.show', [
            'account' => $account,
            'revealed' => $this->shown,
            'myRequest' => $this->myRequest($account),
            'configured' => $vault->isConfigured(),
            'candidates' => auth()->user()?->can('update', $account) === true ? $this->candidates() : [],
            'item' => $account->configuration_item_id !== null ? ($items->find([$account->configuration_item_id])[$account->configuration_item_id] ?? null) : null,
            'application' => $account->application_id !== null ? ($applications->find([$account->application_id])[$account->application_id] ?? null) : null,
            'revealSeconds' => max(5, config()->integer('pam.vault.reveal_seconds', 60)),
        ])->title($account->name);
    }

    private function myRequest(PrivilegedAccount $account): ?AccessRequest
    {
        return $account->requires_approval
            ? AccessRequest::query()->where('account_id', $account->id)->where('user_id', auth()->id())->latest()->first()
            : null;
    }

    /**
     * Active people of the organization who may use the vault, without those who
     * already have access and without the person granting: nobody grants themselves.
     *
     * @return array<string, string>
     */
    private function candidates(): array
    {
        $granted = AccountGrant::query()->where('account_id', $this->accountId)->pluck('user_id')->all();

        return User::query()->where('is_active', true)
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey(app(OrganizationContext::class)->requireId()))
            ->whereKeyNot([...$granted, auth()->id()])
            ->orderBy('name')->get()
            ->filter(fn (User $user): bool => $user->can('pam.vault.use'))
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }

    /**
     * A stolen session must not be able to give itself access, or overwrite or
     * destroy a credential, for which there is no earlier copy.
     */
    private function confirm(VaultSecondFactor $secondFactor, string $purpose): void
    {
        $this->validate(['actionCode' => ['required', 'string', 'max:32']]);
        $code = $this->actionCode;
        $this->reset('actionCode');
        $secondFactor->confirm($this->actor(), $code, $purpose, 'actionCode');
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
