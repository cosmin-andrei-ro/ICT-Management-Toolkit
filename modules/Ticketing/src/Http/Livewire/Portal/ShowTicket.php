<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Links\KnowledgeArticles;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\AddTicketAttachment;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\CancelTicket;
use Modules\Ticketing\Application\Actions\CloseTicket;
use Modules\Ticketing\Application\Actions\ReopenTicket;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Livewire\Concerns\HandlesTicketActions;

/**
 * The requester's view: public comments and attachments only.
 *
 * @property-read Ticket $ticket Computed, see HandlesTicketActions.
 */
#[Layout('core::components.layouts.app')]
final class ShowTicket extends Component
{
    use HandlesTicketActions;
    use WithFileUploads;

    public string $reply = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public string $reopenReason = '';

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticketId = $ticket->id;
    }

    public function sendReply(AddTicketComment $addComment, AddTicketAttachment $addAttachment): void
    {
        $ticket = $this->ticket;
        $this->authorize('comment', $ticket);
        $this->validate([
            'reply' => ['required_without:files', 'nullable', 'string', 'max:20000'],
            'files' => ['array', 'max:5'],
            'files.*' => ['file'],
        ]);

        $sent = $this->attempt(function () use ($ticket, $addComment, $addAttachment): void {
            if (trim($this->reply) !== '') {
                $addComment->handle($ticket, $this->user(), $this->reply, internal: false);
            }

            foreach ($this->files as $file) {
                $addAttachment->handle($ticket, $this->user(), $file, internal: false);
            }
        }, 'reply');

        if ($sent) {
            $this->reset('reply', 'files');
        }
    }

    public function reopen(ReopenTicket $reopenTicket): void
    {
        $this->authorize('reopen', $this->ticket);
        $this->validate(['reopenReason' => ['required', 'string', 'max:5000']]);

        if ($this->attempt(fn () => $reopenTicket->handle($this->ticket, $this->reopenReason, $this->user()))) {
            $this->reset('reopenReason');
        }
    }

    public function close(CloseTicket $closeTicket): void
    {
        $this->authorize('close', $this->ticket);
        $this->attempt(fn () => $closeTicket->handle($this->ticket, $this->user()));
    }

    public function cancel(CancelTicket $cancelTicket): void
    {
        $this->authorize('cancel', $this->ticket);
        $this->attempt(fn () => $cancelTicket->handle($this->ticket, '', $this->user()));
    }

    public function render(KnowledgeArticles $articles): View
    {
        $ticket = $this->ticket;
        $user = auth()->user();

        return view('ticketing::livewire.portal.show', [
            'articles' => $user instanceof User ? $articles->about($ticket->getMorphClass(), $ticket->id, $user) : [],
            'ticket' => $ticket,
            'comments' => $ticket->comments()->where('is_internal', false)->with('author')->get(),
            'attachments' => $ticket->attachments()->where('is_internal', false)->get(),
        ])->title($ticket->number);
    }
}
