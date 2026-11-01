<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Changes;

use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Changes\ChangeCalendar;
use Modules\Ticketing\Application\Changes\DecideOnChange;
use Modules\Ticketing\Domain\Changes\ApprovalDecision;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * What a CAB member sees to decide: the change, its plans and window, overlaps,
 * and the decisions already made in this round.
 *
 * @property-read Ticket $change Computed.
 */
#[Layout('core::components.layouts.app')]
final class ReviewChange extends Component
{
    #[Locked]
    public string $ticketId = '';

    public string $comment = '';

    public function mount(Ticket $ticket): void
    {
        abort_unless($ticket->type === TicketType::Change, 404);
        $this->authorize('view', $ticket);
        $this->ticketId = $ticket->id;
    }

    #[Computed]
    public function change(): Ticket
    {
        return Ticket::query()->where('type', TicketType::Change)->with(['changeDetails.cabBoard', 'requester'])->findOrFail($this->ticketId);
    }

    public function approve(DecideOnChange $decide): void
    {
        $this->decide($decide, ApprovalDecision::Approved);
    }

    public function reject(DecideOnChange $decide): void
    {
        $this->validate(['comment' => ['required', 'string', 'max:5000']]);
        $this->decide($decide, ApprovalDecision::Rejected);
    }

    public function render(ChangeCalendar $calendar, ConfigurationItems $configurationItems): View
    {
        $change = $this->change;
        $details = $change->changeDetails;

        return view('ticketing::livewire.changes.review', [
            'change' => $change,
            'details' => $details,
            'approvals' => $details !== null ? $change->changeApprovals()->where('round', $details->approval_round)->with('user')->orderBy('decided_at')->get() : collect(),
            'conflicts' => $calendar->conflictsWith($change),
            'configurationItems' => [
                'available' => $configurationItems->isAvailable(),
                'linked' => $configurationItems->linkedTo($change),
                'candidates' => [],
                'impact' => $configurationItems->impactOf($change),
            ],
        ])->title($change->number);
    }

    private function decide(DecideOnChange $decide, ApprovalDecision $decision): void
    {
        $this->authorize('decideOnChange', $this->change);
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        try {
            $decide->handle($this->change, $user, $decision, $this->comment);
        } catch (DomainException $exception) {
            $this->addError('comment', $exception->getMessage());

            return;
        }

        unset($this->change);
        $this->reset('comment');
        session()->flash('decided', __('ticketing::changes.ui.decision_recorded'));
    }
}
