<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Desk;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * The service desk's working list: all tickets of the organization, open ones by
 * default, most urgent first.
 */
#[Layout('core::components.layouts.app')]
final class TicketQueue extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $queue = '';

    #[Url]
    public string $priority = '';

    #[Url]
    public string $assignee = 'any';

    #[Url]
    public string $sla = 'any';

    #[Url]
    public string $type = '';

    public function mount(): void
    {
        abort_unless($this->user()->can('ticketing.ticket.view_all'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'queue', 'priority', 'assignee', 'sla', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        abort_unless($this->user()->can('ticketing.ticket.view_all'), 403);

        $statuses = array_map(fn (TicketStatus $status): string => $status->value, TicketStatus::cases());
        $priorities = array_map(fn (Priority $priority): string => $priority->value, Priority::cases());

        $tickets = Ticket::query()
            ->search(mb_substr($this->search, 0, 200))
            ->when($this->status === 'open', fn ($query) => $query->whereIn('status', TicketStatus::open()))
            ->when(in_array($this->status, $statuses, true), fn ($query) => $query->where('status', $this->status))
            ->when($this->queue !== '', fn ($query) => $query->where('queue_id', $this->queue))
            ->when(TicketType::tryFrom($this->type) !== null, fn ($query) => $query->where('type', $this->type))
            ->when(in_array($this->priority, $priorities, true), fn ($query) => $query->where('priority', $this->priority))
            ->when($this->assignee === 'me', fn ($query) => $query->where('assignee_id', $this->user()->id))
            ->when($this->assignee === 'unassigned', fn ($query) => $query->whereNull('assignee_id'))
            ->when($this->sla === 'at_risk', fn ($query) => $query->whereHas('slaTimers', fn ($timers) => $timers
                ->whereNotNull('running_since')
                ->where(fn ($risk) => $risk->whereNotNull('warned_at')->orWhereNotNull('breached_at'))))
            ->when($this->sla === 'breached', fn ($query) => $query->whereHas('slaTimers', fn ($timers) => $timers
                ->whereNotNull('running_since')->whereNotNull('breached_at')))
            ->with(['requester', 'assignee', 'queue', 'slaTimers'])
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->oldest()
            ->paginate(25);

        return view('ticketing::livewire.desk.queue', [
            'tickets' => $tickets,
            'queues' => Queue::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => TicketStatus::cases(),
            'priorities' => Priority::cases(),
            'types' => TicketType::cases(),
        ])->title(__('ticketing::ui.desk.title'));
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
