<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Desk;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Actions\AddTicketAttachment;
use Modules\Ticketing\Application\Actions\AddTicketComment;
use Modules\Ticketing\Application\Actions\AssignTicket;
use Modules\Ticketing\Application\Actions\CancelTicket;
use Modules\Ticketing\Application\Actions\ChangeTicketStatus;
use Modules\Ticketing\Application\Actions\CloseTicket;
use Modules\Ticketing\Application\Actions\ReopenTicket;
use Modules\Ticketing\Application\Actions\ResolveTicket;
use Modules\Ticketing\Application\Actions\UpdateTicketDetails;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Workflow\TicketWorkflow;
use Modules\Ticketing\Http\Livewire\Concerns\HandlesTicketActions;

/**
 * The agent's view: everything, including internal notes, plus triage,
 * assignment, status and resolution.
 *
 * @property-read Ticket $ticket Computed, see HandlesTicketActions.
 */
#[Layout('core::components.layouts.app')]
final class ShowTicket extends Component
{
    use HandlesTicketActions;
    use WithFileUploads;

    public string $comment = '';

    public bool $internal = false;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public string $queueId = '';

    public string $assigneeId = '';

    public string $resolutionCode = 'fixed';

    public string $resolutionNote = '';

    public string $title = '';

    public string $description = '';

    public string $impact = 'low';

    public string $urgency = 'low';

    public ?string $categoryId = null;

    public string $reason = '';

    public function mount(Ticket $ticket): void
    {
        $this->authorize('work', $ticket);
        $this->ticketId = $ticket->id;
        $this->fillFromTicket($ticket);
    }

    public function addComment(AddTicketComment $addComment, AddTicketAttachment $addAttachment): void
    {
        $ticket = $this->ticket;
        $this->authorize($this->internal ? 'commentInternally' : 'comment', $ticket);
        $this->validate([
            'comment' => ['required_without:files', 'nullable', 'string', 'max:20000'],
            'files' => ['array', 'max:5'],
            'files.*' => ['file'],
        ]);

        $sent = $this->attempt(function () use ($ticket, $addComment, $addAttachment): void {
            if (trim($this->comment) !== '') {
                $addComment->handle($ticket, $this->user(), $this->comment, $this->internal);
            }

            foreach ($this->files as $file) {
                $addAttachment->handle($ticket, $this->user(), $file, $this->internal);
            }
        }, 'comment');

        if ($sent) {
            $this->reset('comment', 'files', 'internal');
        }
    }

    public function assign(AssignTicket $assignTicket): void
    {
        $ticket = $this->ticket;
        $this->authorize('assign', $ticket);
        $this->validate([
            'queueId' => ['nullable', 'uuid'],
            'assigneeId' => ['nullable', 'uuid'],
        ]);

        // Scoped lookups: ids from another organization resolve to nothing.
        $queue = $this->queueId !== '' ? Queue::query()->findOrFail($this->queueId) : null;
        $assignee = $this->assigneeId !== '' ? User::query()->findOrFail($this->assigneeId) : null;

        $this->attempt(fn () => $assignTicket->handle($ticket, $assignee, $queue, $this->user()), 'assign');
    }

    public function assignToMe(AssignTicket $assignTicket): void
    {
        $this->authorize('work', $this->ticket);
        $this->attempt(fn () => $assignTicket->handle($this->ticket, $this->user(), null, $this->user()), 'assign');
        $this->fillFromTicket($this->ticket);
    }

    public function changeStatus(string $status, ChangeTicketStatus $changeStatus): void
    {
        $this->authorize('work', $this->ticket);
        $target = TicketStatus::tryFrom($status);
        abort_if($target === null, 422);

        $this->attempt(fn () => $changeStatus->handle($this->ticket, $target, $this->user()));
        $this->fillFromTicket($this->ticket);
    }

    public function resolve(ResolveTicket $resolveTicket): void
    {
        $this->authorize('work', $this->ticket);
        $this->validate([
            'resolutionCode' => ['required', Rule::enum(ResolutionCode::class)],
            'resolutionNote' => ['required', 'string', 'max:20000'],
        ]);

        if ($this->attempt(fn () => $resolveTicket->handle($this->ticket, ResolutionCode::from($this->resolutionCode), $this->resolutionNote, $this->user()), 'resolutionNote')) {
            $this->reset('resolutionNote');
        }
    }

    public function updateDetails(UpdateTicketDetails $updateDetails): void
    {
        $this->authorize('work', $this->ticket);
        $this->validate([
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'description' => ['required', 'string', 'max:20000'],
            'impact' => ['required', Rule::enum(Impact::class)],
            'urgency' => ['required', Rule::enum(Urgency::class)],
            'categoryId' => ['nullable', 'uuid'],
        ]);

        $this->attempt(fn () => $updateDetails->handle(
            $this->ticket,
            $this->title,
            $this->description,
            Impact::from($this->impact),
            Urgency::from($this->urgency),
            $this->categoryId !== '' ? $this->categoryId : null,
        ), 'title');
    }

    public function reopen(ReopenTicket $reopenTicket): void
    {
        $this->authorize('reopen', $this->ticket);
        $this->attempt(fn () => $reopenTicket->handle($this->ticket, $this->reason, $this->user()));
        $this->reset('reason');
    }

    public function close(CloseTicket $closeTicket): void
    {
        $this->authorize('close', $this->ticket);
        $this->attempt(fn () => $closeTicket->handle($this->ticket, $this->user()));
    }

    public function cancel(CancelTicket $cancelTicket): void
    {
        $this->authorize('cancel', $this->ticket);
        $this->validate(['reason' => ['required', 'string', 'max:5000']]);

        if ($this->attempt(fn () => $cancelTicket->handle($this->ticket, $this->reason, $this->user()), 'reason')) {
            $this->reset('reason');
        }
    }

    public function render(TicketWorkflow $workflow): View
    {
        $ticket = $this->ticket;
        $queue = $this->queueId !== '' ? Queue::query()->with('team.members')->find($this->queueId) : null;

        return view('ticketing::livewire.desk.show', [
            'ticket' => $ticket,
            'comments' => $ticket->comments()->with('author')->get(),
            'attachments' => $ticket->attachments()->get(),
            'transitions' => $ticket->statusTransitions()->with('user')->get(),
            'workingTargets' => array_values(array_filter(
                $workflow->allowedFrom($ticket->status),
                fn (TicketStatus $status): bool => in_array($status, [TicketStatus::New, TicketStatus::InProgress, TicketStatus::Pending], true),
            )),
            'queues' => Queue::query()->where('is_active', true)->orderBy('name')->get(),
            // Assignment requires queue team membership; offer only those people.
            'assignees' => $queue?->team->members->sortBy('name')->values() ?? collect(),
            'categories' => Category::query()->where('is_active', true)->with('parent')->orderBy('name')->get(),
            'resolutionCodes' => ResolutionCode::cases(),
            'impacts' => Impact::cases(),
            'urgencies' => Urgency::cases(),
        ])->title($ticket->number);
    }

    private function fillFromTicket(Ticket $ticket): void
    {
        $this->queueId = $ticket->queue_id ?? '';
        $this->assigneeId = $ticket->assignee_id ?? '';
        $this->title = $ticket->title;
        $this->description = $ticket->description;
        $this->impact = $ticket->impact->value;
        $this->urgency = $ticket->urgency->value;
        $this->categoryId = $ticket->category_id;
    }
}
