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
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * The known error database (KEDB): problems with a documented workaround that are
 * not closed yet.
 */
#[Layout('core::components.layouts.app')]
final class KnownErrors extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->ensureAllowed();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->ensureAllowed();
        $terms = trim(mb_substr($this->search, 0, 200));
        $like = '%'.addcslashes($terms, '%_\\').'%';

        $knownErrors = Ticket::query()
            ->where('type', TicketType::Problem)
            ->whereNotIn('status', [TicketStatus::Closed, TicketStatus::Cancelled])
            ->whereHas('problemDetails', fn ($details) => $details->whereNotNull('known_error_at'))
            ->when($terms !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->search($terms)
                ->orWhereHas('problemDetails', fn ($details) => $details
                    ->whereRaw('workaround ILIKE ?', [$like])
                    ->orWhereRaw('root_cause ILIKE ?', [$like]))))
            ->with('problemDetails')
            ->withCount('incidents')
            ->orderByDesc('incidents_count')
            ->paginate(20);

        return view('ticketing::livewire.desk.known-errors', ['knownErrors' => $knownErrors])
            ->title(__('ticketing::problems.ui.known_errors'));
    }

    private function ensureAllowed(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('ticketing.ticket.view_all'), 403);
    }
}
