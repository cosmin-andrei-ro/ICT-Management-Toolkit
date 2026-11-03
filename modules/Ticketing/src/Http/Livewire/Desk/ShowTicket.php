<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Desk;

use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LogicException;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Links\KnowledgeArticles;
use Modules\Core\Domain\Links\LinkedRecord;
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
use Modules\Ticketing\Application\Changes\ChangeCalendar;
use Modules\Ticketing\Application\Changes\ChangePlan;
use Modules\Ticketing\Application\Changes\RecordChangeReview;
use Modules\Ticketing\Application\Changes\SubmitChange;
use Modules\Ticketing\Application\Changes\UpdateChangePlan;
use Modules\Ticketing\Application\Problems\CreateProblem;
use Modules\Ticketing\Application\Problems\LinkIncidentToProblem;
use Modules\Ticketing\Application\Problems\MarkKnownError;
use Modules\Ticketing\Application\Problems\ResolveProblem;
use Modules\Ticketing\Application\Problems\UpdateProblemAnalysis;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\ResolutionCode;
use Modules\Ticketing\Domain\Enums\TicketStatus;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\CabBoard;
use Modules\Ticketing\Domain\Models\Category;
use Modules\Ticketing\Domain\Models\MonitoringAlert;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Domain\Workflow\TicketWorkflow;
use Modules\Ticketing\Http\Livewire\Concerns\EditsChangePlan;
use Modules\Ticketing\Http\Livewire\Concerns\HandlesTicketActions;
use Modules\Ticketing\Http\Livewire\Concerns\LinksConfigurationItems;

/**
 * The agent's view: everything, including internal notes, plus triage,
 * assignment, status and resolution.
 *
 * @property-read Ticket $ticket Computed, see HandlesTicketActions.
 */
#[Layout('core::components.layouts.app')]
final class ShowTicket extends Component
{
    use EditsChangePlan;
    use HandlesTicketActions;
    use LinksConfigurationItems;
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

    public string $problemId = '';

    public string $rootCause = '';

    public string $workaround = '';

    public bool $resolveIncidents = true;

    public string $boardId = '';

    public string $review = '';

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
        $ticket = $this->ticket;
        $this->authorize($ticket->type === TicketType::Problem ? 'manageProblem' : 'work', $ticket);
        $this->validate([
            'resolutionCode' => ['required', Rule::enum(ResolutionCode::class)],
            'resolutionNote' => ['required', 'string', 'max:20000'],
        ]);

        $code = ResolutionCode::from($this->resolutionCode);
        $resolved = $ticket->type === TicketType::Problem
            ? $this->attempt(fn () => app(ResolveProblem::class)->handle($ticket, $code, $this->resolutionNote, $this->resolveIncidents, $this->user()), 'resolutionNote')
            : $this->attempt(fn () => $resolveTicket->handle($ticket, $code, $this->resolutionNote, $this->user()), 'resolutionNote');

        if ($resolved) {
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

    public function linkProblem(LinkIncidentToProblem $link): void
    {
        $this->authorize('linkProblem', $this->ticket);
        $this->validate(['problemId' => ['required', 'uuid']]);

        // Scoped lookup: problems of other organizations do not exist here.
        $problem = Ticket::query()->where('type', TicketType::Problem)->findOrFail($this->problemId);
        $this->attempt(fn () => $link->handle($this->ticket, $problem->load('problemDetails'), $this->user()), 'problemId');
    }

    public function unlinkProblem(LinkIncidentToProblem $link): void
    {
        $this->authorize('linkProblem', $this->ticket);
        $this->attempt(fn () => $link->handle($this->ticket, null, $this->user()), 'problemId');
        $this->reset('problemId');
    }

    public function createProblemFromIncident(CreateProblem $createProblem): void
    {
        $incident = $this->ticket;
        $this->authorize('linkProblem', $incident);
        $this->authorize('createProblem', Ticket::class);

        $problem = $createProblem->handle($incident->title, $incident->description, $incident->impact, $incident->urgency, $this->user(), [$incident]);

        $this->redirectRoute('ticketing.desk.show', $problem);
    }

    public function saveAnalysis(UpdateProblemAnalysis $update): void
    {
        $this->authorize('manageProblem', $this->ticket);
        $this->validate([
            'rootCause' => ['nullable', 'string', 'max:'.UpdateProblemAnalysis::MAX_LENGTH],
            'workaround' => ['nullable', 'string', 'max:'.UpdateProblemAnalysis::MAX_LENGTH],
        ]);

        $this->attempt(fn () => $update->handle($this->ticket, $this->rootCause, $this->workaround), 'workaround');
    }

    public function markKnownError(MarkKnownError $mark): void
    {
        $this->authorize('manageProblem', $this->ticket);
        $this->attempt(fn () => $mark->handle($this->ticket), 'workaround');
    }

    public function saveChangePlan(UpdateChangePlan $update): void
    {
        $this->authorize('manageChange', $this->ticket);
        $this->validate($this->planRules());

        $saved = $this->attempt(function () use ($update): void {
            if ($update->handle($this->ticket, $this->toPlan(), $this->user())) {
                session()->flash('change_notice', __('ticketing::changes.ui.approvals_withdrawn'));
            }
        }, 'plannedStart');

        if ($saved) {
            $this->fillPlan($this->ticket->changeDetails ?? throw new LogicException('Not a change.'));
        }
    }

    public function submitChange(SubmitChange $submit): void
    {
        $this->authorize('manageChange', $this->ticket);
        $this->validate(['boardId' => ['required', 'uuid']]);

        // Scoped lookup: boards of other organizations do not exist here.
        $board = CabBoard::query()->findOrFail($this->boardId);

        try {
            $submit->handle($this->ticket, $board, $this->user());
        } catch (DomainException $exception) {
            $this->addError('boardId', $exception->getMessage());

            return;
        }

        unset($this->ticket);
    }

    public function recordReview(RecordChangeReview $record): void
    {
        $this->authorize('manageChange', $this->ticket);
        $this->validate(['review' => ['required', 'string', 'max:'.ChangePlan::MAX_LENGTH]]);

        if ($this->attempt(fn () => $record->handle($this->ticket, $this->review), 'review')) {
            $this->reset('review');
        }
    }

    public function render(TicketWorkflow $workflow, ConfigurationItems $configurationItems, KnowledgeArticles $articles): View
    {
        $ticket = $this->ticket;
        $viewer = auth()->user();
        $linkedArticles = $viewer instanceof User ? $articles->about($ticket->getMorphClass(), $ticket->id, $viewer) : [];
        $linkedIds = array_map(fn (LinkedRecord $article): string => $article->id, $linkedArticles);
        $queue = $this->queueId !== '' ? Queue::query()->with('team.members')->find($this->queueId) : null;
        // Working a ticket and reading its internal notes are separate permissions, as in the API.
        $showInternal = $viewer instanceof User && $viewer->can('viewInternal', $ticket);

        return view('ticketing::livewire.desk.show', [
            'ticket' => $ticket,
            'showInternal' => $showInternal,
            'configurationItems' => $this->configurationItemData($configurationItems, $ticket),
            'comments' => $ticket->comments()->when(! $showInternal, fn (Builder $query) => $query->where('is_internal', false))->with('author')->get(),
            'attachments' => $ticket->attachments()->when(! $showInternal, fn (Builder $query) => $query->where('is_internal', false))->get(),
            'transitions' => $ticket->statusTransitions()->with('user')->get(),
            'slaTimers' => $ticket->slaTimers()->orderBy('metric', 'desc')->get(),
            'monitoringAlerts' => MonitoringAlert::query()->where('ticket_id', $ticket->id)->orderByDesc('first_seen_at')->get(),
            'workingTargets' => array_values(array_filter(
                $workflow->allowedFrom($ticket->status),
                fn (TicketStatus $status): bool => in_array($status, [TicketStatus::New, TicketStatus::InProgress, TicketStatus::Pending], true),
            )),
            'queues' => Queue::query()->where('is_active', true)->orderBy('name')->get(),
            // Assignment requires queue team membership; offer only those people.
            'assignees' => $queue?->team->members->sortBy('name')->values() ?? collect(),
            'categories' => Category::query()->where('is_active', true)->with('parent')->orderBy('name')->get(),
            'resolutionCodes' => ResolutionCode::forType($ticket->type),
            'risks' => ChangeRisk::cases(),
            'boards' => $ticket->changeDetails !== null
                ? CabBoard::query()->where('is_active', true)->where('is_emergency', $ticket->changeDetails->change_type === ChangeType::Emergency)->orderBy('name')->get()
                : collect(),
            'approvals' => $ticket->changeDetails !== null
                ? $ticket->changeApprovals()->where('round', $ticket->changeDetails->approval_round)->with('user')->orderBy('decided_at')->get()
                : collect(),
            'conflicts' => $ticket->changeDetails !== null ? app(ChangeCalendar::class)->conflictsWith($ticket) : collect(),
            'linkedIncidents' => $ticket->type === TicketType::Problem
                ? $ticket->incidents()->with('requester')->latest()->get()
                : collect(),
            'openProblems' => $ticket->type === TicketType::Incident
                ? Ticket::query()->where('type', TicketType::Problem)->whereNotIn('status', [TicketStatus::Closed, TicketStatus::Cancelled])->orderByDesc('created_at')->limit(100)->get()
                : collect(),
            'impacts' => Impact::cases(),
            'urgencies' => Urgency::cases(),
            'articles' => $linkedArticles,
            'suggestedArticles' => $viewer instanceof User
                ? array_values(array_filter($articles->suggest($ticket->title, $viewer), fn (LinkedRecord $article): bool => ! in_array($article->id, $linkedIds, true)))
                : [],
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
        $this->problemId = $ticket->problem_id ?? '';

        if ($ticket->changeDetails !== null) {
            $this->fillPlan($ticket->changeDetails);
            $this->boardId = $ticket->changeDetails->cab_board_id ?? '';
        }
        $this->rootCause = $ticket->problemDetails->root_cause ?? '';
        $this->workaround = $ticket->problemDetails->workaround ?? '';
    }
}
