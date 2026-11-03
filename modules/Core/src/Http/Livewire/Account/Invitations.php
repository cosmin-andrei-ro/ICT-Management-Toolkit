<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Account;

use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Application\Users\AcceptOrganizationInvitation;
use Modules\Core\Domain\Authorization\Role;
use Modules\Core\Domain\Models\OrganizationInvitation;
use Modules\Core\Domain\Models\User;

/**
 * Invitations from other organizations addressed to the signed-in person.
 * Every action looks the invitation up among their own.
 */
#[Layout('core::components.layouts.app')]
final class Invitations extends Component
{
    public function accept(string $invitationId, AcceptOrganizationInvitation $answer): void
    {
        $invitation = $this->find($invitationId);

        try {
            $answer->accept($invitation, $this->user());
        } catch (DomainException) {
            session()->flash('invitation_error', __('core::invitations.refused_sso'));

            return;
        }

        session()->flash('invitation_status', __('core::invitations.accepted', ['organization' => $invitation->organization->name]));
    }

    public function decline(string $invitationId, AcceptOrganizationInvitation $answer): void
    {
        $answer->decline($this->find($invitationId), $this->user());
        session()->flash('invitation_status', __('core::invitations.declined'));
    }

    public function render(): View
    {
        $invitations = OrganizationInvitation::pendingFor($this->user())->with(['organization', 'inviter'])->orderBy('created_at')->get();
        $roleIds = $invitations->pluck('role_ids')->flatten()->unique()->values()->all();

        return view('core::livewire.account.invitations', [
            'invitations' => $invitations,
            'roleNames' => Role::query()->whereKey($roleIds)->pluck('name', 'id')->all(),
        ])->title(__('core::invitations.title'));
    }

    private function find(string $invitationId): OrganizationInvitation
    {
        return OrganizationInvitation::pendingFor($this->user())->with('organization')->findOrFail($invitationId);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
