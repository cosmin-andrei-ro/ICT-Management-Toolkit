<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Pam\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Links\LinkedRecords;
use Modules\Core\Domain\Models\User;
use Modules\Pam\Application\PamVault;
use Modules\Pam\Domain\Models\PrivilegedAccount;

/**
 * The accounts someone may see: all of them for those who run the vault, otherwise
 * the ones they own or were granted.
 */
#[Layout('core::components.layouts.app')]
final class AccountIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', PrivilegedAccount::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(PamVault $vault): View
    {
        $this->authorize('viewAny', PrivilegedAccount::class);
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $term = trim(mb_substr($this->search, 0, 200));

        return view('pam::livewire.index', [
            'accounts' => PrivilegedAccount::query()->with('owner')
                ->when(! $user->can('pam.account.manage'), fn (Builder $query) => $query->where(fn (Builder $mine) => $mine
                    ->where('owner_id', $user->id)
                    ->orWhereHas('grants', fn (Builder $grants) => $grants->where('user_id', $user->id))))
                ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                    ->whereLike('name', LinkedRecords::like($term))->orWhereLike('username', LinkedRecords::like($term))))
                ->orderBy('name')
                ->paginate(25),
            'configured' => $vault->isConfigured(),
        ])->title(__('pam::accounts.title'));
    }
}
