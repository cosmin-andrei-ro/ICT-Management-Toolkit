<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Http\Livewire\Changes;

use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Core\Domain\Models\User;
use Modules\Ticketing\Application\Changes\CreateChange as CreateChangeAction;
use Modules\Ticketing\Domain\Changes\ChangeRisk;
use Modules\Ticketing\Domain\Changes\ChangeType;
use Modules\Ticketing\Domain\Models\ChangeTemplate;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Livewire\Concerns\EditsChangePlan;

#[Layout('core::components.layouts.app')]
final class CreateChange extends Component
{
    use EditsChangePlan;

    public string $type = 'normal';

    public string $templateId = '';

    public string $title = '';

    public string $description = '';

    public function mount(): void
    {
        $this->authorize('createChange', Ticket::class);
    }

    public function save(CreateChangeAction $createChange): void
    {
        $this->authorize('createChange', Ticket::class);
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $this->validate([
            'type' => ['required', Rule::enum(ChangeType::class)],
            'templateId' => [Rule::requiredIf($this->type === ChangeType::Standard->value), 'nullable', 'uuid'],
            'title' => [Rule::requiredIf($this->type !== ChangeType::Standard->value), 'nullable', 'string', 'min:5', 'max:200'],
            'description' => [Rule::requiredIf($this->type !== ChangeType::Standard->value), 'nullable', 'string', 'max:20000'],
            ...$this->planRules(),
        ]);

        try {
            $plan = $this->toPlan();
            $change = $this->type === ChangeType::Standard->value
                // Scoped lookup: another organization's template does not exist here.
                ? $createChange->fromTemplate(ChangeTemplate::query()->findOrFail($this->templateId), $this->description, $plan->plannedStart, $plan->plannedEnd, $user)
                : $createChange->handle(ChangeType::from($this->type), $this->title, $this->description, $plan, $user);
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('type', $exception->getMessage());

            return;
        }

        $this->redirectRoute('ticketing.desk.show', $change);
    }

    public function render(): View
    {
        return view('ticketing::livewire.changes.create', [
            'types' => ChangeType::cases(),
            'risks' => ChangeRisk::cases(),
            'templates' => ChangeTemplate::query()->where('is_active', true)->orderBy('name')->get(),
        ])->title(__('ticketing::changes.ui.new'));
    }
}
