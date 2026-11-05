<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Links\Applications;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Pam\Domain\Enums\AccountType;
use Modules\Pam\Domain\Models\PrivilegedAccount;

#[Layout('core::components.layouts.app')]
final class AccountForm extends Component
{
    #[Locked]
    public ?string $accountId = null;

    public string $name = '';

    public string $username = '';

    public string $type = 'service';

    public string $description = '';

    public string $ownerId = '';

    public string $configurationItemId = '';

    public string $applicationId = '';

    public string $itemSearch = '';

    public string $applicationSearch = '';

    public function mount(?PrivilegedAccount $account = null): void
    {
        if ($account === null || ! $account->exists) {
            $this->authorize('create', PrivilegedAccount::class);

            return;
        }

        $this->authorize('update', $account);
        $this->accountId = $account->id;
        $this->name = $account->name;
        $this->username = $account->username;
        $this->type = $account->type->value;
        $this->description = $account->description ?? '';
        $this->ownerId = $account->owner_id ?? '';
        $this->configurationItemId = $account->configuration_item_id ?? '';
        $this->applicationId = $account->application_id ?? '';
    }

    public function pickItem(string $id): void
    {
        $this->configurationItemId = $id;
        $this->itemSearch = '';
    }

    public function pickApplication(string $id): void
    {
        $this->applicationId = $id;
        $this->applicationSearch = '';
    }

    public function save(ConfigurationItems $items, Applications $applications): void
    {
        $account = $this->accountId !== null ? PrivilegedAccount::query()->findOrFail($this->accountId) : null;
        $this->authorize($account !== null ? 'update' : 'create', $account ?? PrivilegedAccount::class);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'ownerId' => ['nullable', 'uuid', Rule::in(array_keys($this->people()))],
            'configurationItemId' => ['nullable', 'uuid'],
            'applicationId' => ['nullable', 'uuid'],
        ]);

        // Through the contracts: an id of another organization is simply not found.
        $itemId = $this->configurationItemId !== '' && $items->find([$this->configurationItemId]) !== [] ? $this->configurationItemId : null;
        $applicationId = $this->applicationId !== '' && $applications->find([$this->applicationId]) !== [] ? $this->applicationId : null;

        $account ??= new PrivilegedAccount;
        $account->fill([
            'name' => trim($this->name),
            'username' => trim($this->username),
            'type' => $this->type,
            'description' => $this->description !== '' ? $this->description : null,
            'owner_id' => $this->ownerId !== '' ? $this->ownerId : null,
            'configuration_item_id' => $itemId,
            'application_id' => $applicationId,
        ])->save();

        $this->redirectRoute('pam.accounts.show', $account);
    }

    public function render(ConfigurationItems $items, Applications $applications): View
    {
        return view('pam::livewire.form', [
            'types' => AccountType::cases(),
            'people' => $this->people(),
            'itemsAvailable' => $items->isAvailable(),
            'applicationsAvailable' => $applications->isAvailable(),
            'itemResults' => mb_strlen(trim($this->itemSearch)) >= 2 ? $items->search($this->itemSearch, 8) : [],
            'applicationResults' => mb_strlen(trim($this->applicationSearch)) >= 2 ? $applications->search($this->applicationSearch, 8) : [],
            'item' => $this->configurationItemId !== '' ? ($items->find([$this->configurationItemId])[$this->configurationItemId] ?? null) : null,
            'application' => $this->applicationId !== '' ? ($applications->find([$this->applicationId])[$this->applicationId] ?? null) : null,
        ])->title(__($this->accountId !== null ? 'pam::accounts.ui.edit' : 'pam::accounts.ui.new'));
    }

    /**
     * @return array<string, string>
     */
    private function people(): array
    {
        // Owning an account means keeping it: only people who may use the vault.
        return User::query()->where('is_active', true)
            ->whereHas('organizations', fn (Builder $query) => $query->whereKey(app(OrganizationContext::class)->requireId()))
            ->orderBy('name')->get()
            ->filter(fn (User $user): bool => $user->can('pam.vault.use'))
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }
}
