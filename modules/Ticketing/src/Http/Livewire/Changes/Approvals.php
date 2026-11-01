<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Changes;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Changes\ApprovalStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * CAB/ECAB inbox: changes waiting for this person's decision. Usable by board
 * members who do not work in the service desk.
 */
#[Layout('core::components.layouts.app')]
final class Approvals extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('ticketing.change.approve'), 403);

        $waiting = Ticket::query()
            ->where('type', TicketType::Change)
            ->whereHas('changeDetails', fn ($details) => $details
                ->where('approval_status', ApprovalStatus::Pending)
                ->whereHas('cabBoard.members', fn ($members) => $members->whereKey($user->id)))
            ->with('changeDetails.cabBoard', 'requester')
            ->get()
            ->filter(fn (Ticket $change): bool => Gate::forUser($user)->allows('decideOnChange', $change))
            ->sortBy(fn (Ticket $change) => $change->changeDetails?->planned_start)
            ->values();

        return view('ticketing::livewire.changes.approvals', ['waiting' => $waiting])
            ->title(__('ticketing::changes.ui.approvals'));
    }
}
