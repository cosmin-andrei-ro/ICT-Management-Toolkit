<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\RequestApprovals;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\DecideServiceRequest;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * One catalog request, as its approver needs to see it: what was asked, by whom.
 * Internal notes stay out, since an approver need not work in Ticketing.
 *
 * @property-read Ticket $request
 */
#[Layout('core::components.layouts.app')]
final class ReviewServiceRequest extends Component
{
    #[Locked]
    public string $ticketId = '';

    public string $comment = '';

    public function mount(string $ticket): void
    {
        $this->ticketId = $ticket;
        $this->authorize('decideRequest', $this->request);
    }

    #[Computed]
    public function request(): Ticket
    {
        return Ticket::query()->with(['requester', 'service'])->findOrFail($this->ticketId);
    }

    public function approve(DecideServiceRequest $decide): void
    {
        $this->validate(['comment' => ['nullable', 'string', 'max:5000']]);
        $this->decide($decide, true);
    }

    public function reject(DecideServiceRequest $decide): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:5000']]);
        $this->decide($decide, false);
    }

    public function render(): View
    {
        return view('ticketing::livewire.request-approvals.review')->title($this->request->number);
    }

    private function decide(DecideServiceRequest $decide, bool $approved): void
    {
        $this->authorize('decideRequest', $this->request);
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $decide->handle($this->request, $user, $approved, $this->comment);
        } catch (InvalidArgumentException) {
            abort(409);
        }

        session()->flash('status', __($approved ? 'ticketing::catalog.approval.approved' : 'ticketing::catalog.approval.rejected'));
        $this->redirectRoute('ticketing.request-approvals');
    }
}
