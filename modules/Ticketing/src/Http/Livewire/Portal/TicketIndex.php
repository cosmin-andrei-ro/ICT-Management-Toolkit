<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Models\Ticket;

#[Layout('core::components.layouts.app')]
final class TicketIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $show = 'open';

    public function mount(): void
    {
        $this->authorize('create', Ticket::class);
    }

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $tickets = Ticket::query()
            ->visibleToRequester($user)
            ->when($this->show === 'open', fn ($query) => $query->whereIn('status', TicketStatus::open()))
            ->when($this->show === 'closed', fn ($query) => $query->whereNotIn('status', TicketStatus::open()))
            ->with('category')
            ->latest()
            ->paginate(15);

        return view('ticketing::livewire.portal.index', ['tickets' => $tickets])
            ->title(__('ticketing::ui.portal.title'));
    }
}
