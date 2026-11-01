<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Concerns;

use Closure;
use DomainException;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Domain\Models\Ticket;

/**
 * Shared by the portal and desk ticket pages. The ticket id is locked so the
 * browser cannot swap it, and the ticket is re-read (organization-scoped) on
 * every request.
 */
trait HandlesTicketActions
{
    #[Locked]
    public string $ticketId = '';

    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::query()->with(['requester', 'assignee', 'queue', 'category.parent'])->findOrFail($this->ticketId);
    }

    protected function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * Runs a domain action, turning rule violations into a form error instead of a 500.
     *
     * @param  Closure(): mixed  $action
     */
    protected function attempt(Closure $action, string $errorKey = 'action'): bool
    {
        try {
            $action();
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError($errorKey, __('ticketing::ui.action_failed'));
            report($exception);

            return false;
        }

        unset($this->ticket);

        return true;
    }
}
