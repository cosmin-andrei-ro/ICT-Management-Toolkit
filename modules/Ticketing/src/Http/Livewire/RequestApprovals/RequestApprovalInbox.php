<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\RequestApprovals;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Catalog requests waiting for this person's decision. People who configure
 * Ticketing see them all, so a request cannot be stuck when its approver has left.
 */
#[Layout('core::components.layouts.app')]
final class RequestApprovalInbox extends Component
{
    use WithPagination;

    public static function isAvailableTo(User $user): bool
    {
        return $user->can('ticketing.settings.manage') || Service::query()->whereHas('approvers', fn ($approvers) => $approvers->whereKey($user->id))->exists();
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User && self::isAvailableTo($user), 403);

        return view('ticketing::livewire.request-approvals.inbox', [
            'requests' => Ticket::query()
                ->where('type', TicketType::ServiceRequest)
                ->where('status', TicketStatus::AwaitingApproval)
                ->where('requester_id', '!=', $user->id)
                ->when(! $user->can('ticketing.settings.manage'), fn ($query) => $query->whereHas('service.approvers', fn ($approvers) => $approvers->whereKey($user->id)))
                ->with('requester')
                ->orderBy('created_at')
                ->paginate(25),
        ])->title(__('ticketing::catalog.approval.title'));
    }
}
