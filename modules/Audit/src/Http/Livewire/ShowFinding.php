<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Audit\Application\Actions\CloseFinding;
use Modules\Audit\Application\Actions\ManageFindingActions;
use Modules\Audit\Domain\Enums\ActionStatus;
use Modules\Audit\Domain\Models\Finding;
use Modules\Audit\Domain\Models\FindingAction;
use Modules\Core\Domain\Links\ProblemTickets;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * One finding: what was found, its root cause and corrective actions, worked
 * by its owner, and its verification by a compliance manager.
 *
 * @property-read Finding $finding Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowFinding extends Component
{
    #[Locked]
    public string $findingId = '';

    public string $rootCause = '';

    public string $actionTitle = '';

    public string $actionOwnerId = '';

    public string $actionDueOn = '';

    public string $verificationNotes = '';

    public function mount(Finding $finding): void
    {
        $this->authorize('view', $finding);
        $this->findingId = $finding->id;
        $this->rootCause = $finding->root_cause ?? '';
    }

    #[Computed]
    public function finding(): Finding
    {
        return Finding::query()->with(['owner', 'audit', 'control', 'requirement.framework', 'verifier'])->findOrFail($this->findingId);
    }

    public function saveRootCause(CloseFinding $close): void
    {
        $this->authorize('work', $this->finding);
        $this->validate(['rootCause' => ['nullable', 'string', 'max:10000']]);
        $close->rootCause($this->finding, $this->rootCause);
        unset($this->finding);
    }

    public function addAction(ManageFindingActions $actions, OrganizationContext $context): void
    {
        $this->authorize('work', $this->finding);
        $this->validate([
            'actionTitle' => ['required', 'string', 'max:255'],
            'actionOwnerId' => ['required', 'uuid', Rule::in($this->people($context)->pluck('id')->all())],
            'actionDueOn' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $actions->add($this->finding, $this->actionTitle, $this->actionOwnerId, $this->actionDueOn !== '' ? (CarbonImmutable::createFromFormat('!Y-m-d', $this->actionDueOn) ?: null) : null, $this->actor());
        $this->reset('actionTitle', 'actionOwnerId', 'actionDueOn');
    }

    public function setActionStatus(string $actionId, string $status, ManageFindingActions $actions): void
    {
        $action = $this->action($actionId);
        $this->authorize('progressAction', [$this->finding, $action]);
        $actions->changeStatus($action, ActionStatus::tryFrom($status) ?? $action->status, $this->actor());
    }

    public function raiseTicket(string $actionId, ManageFindingActions $actions): void
    {
        $action = $this->action($actionId);
        $this->authorize('progressAction', [$this->finding, $action]);

        try {
            $actions->raiseTicket($action, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('actionTitle', __('audit::audits.errors.ticket'));
        }
    }

    public function submit(CloseFinding $close): void
    {
        $this->authorize('work', $this->finding);
        $this->resetErrorBag();
        $close->rootCause($this->finding, $this->rootCause);

        try {
            $close->submit($this->finding, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('rootCause', __('audit::audits.errors.not_ready'));

            return;
        }

        unset($this->finding);
    }

    public function verify(bool $effective, CloseFinding $close): void
    {
        $this->authorize('verify', $this->finding);
        $this->resetErrorBag();
        $this->validate(['verificationNotes' => [$effective ? 'nullable' : 'required', 'string', 'max:10000']]);
        $close->verify($this->finding, $effective, $this->verificationNotes, $this->actor());
        $this->reset('verificationNotes');
        unset($this->finding);
    }

    public function render(OrganizationContext $context, ProblemTickets $tickets): View
    {
        $finding = $this->finding;
        $this->authorize('view', $finding);
        $viewer = $this->actor();
        $canWork = $viewer->can('work', $finding);

        return view('audit::livewire.findings.show', [
            'finding' => $finding,
            'actions' => $finding->actions()->with('owner')->get(),
            'canWork' => $canWork,
            'people' => $canWork ? $this->people($context) : collect(),
            'statuses' => ActionStatus::cases(),
            'ticketingAvailable' => $tickets->isAvailable(),
            'today' => CarbonImmutable::today(),
        ])->title($finding->code().' · '.$finding->title);
    }

    private function action(string $id): FindingAction
    {
        return FindingAction::query()->where('finding_id', $this->findingId)->findOrFail($id);
    }

    /**
     * @return Collection<int, User>
     */
    private function people(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
