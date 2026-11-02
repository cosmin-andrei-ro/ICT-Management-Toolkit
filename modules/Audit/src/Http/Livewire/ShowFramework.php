<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Audit\Application\Actions\ManageRequirements;
use Modules\Audit\Application\Frameworks\Coverage;
use Modules\Audit\Domain\Enums\RequirementState;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Models\User;

/**
 * A framework's requirements, grouped, with their applicability (the
 * Statement of Applicability for ISO/IEC 27001) and the controls meeting them.
 *
 * @property-read Framework $framework Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowFramework extends Component
{
    #[Locked]
    public string $frameworkId = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $state = '';

    /** The requirement whose applicability is being decided. */
    #[Locked]
    public ?string $decidingId = null;

    /** yes, no, or empty for undecided. */
    public string $applicability = '';

    public string $justification = '';

    public string $requirementText = '';

    public string $newReference = '';

    public string $newTitle = '';

    public string $newGroup = '';

    public string $newDescription = '';

    public function mount(Framework $framework): void
    {
        $this->authorize('view', $framework);
        $this->frameworkId = $framework->id;
    }

    #[Computed]
    public function framework(): Framework
    {
        return Framework::query()->findOrFail($this->frameworkId);
    }

    public function decide(string $requirementId): void
    {
        $this->authorize('update', $this->framework);
        $requirement = $this->requirement($requirementId);
        $this->resetErrorBag();
        $this->decidingId = $requirement->id;
        $this->applicability = match ($requirement->is_applicable) {
            true => 'yes',
            false => 'no',
            null => '',
        };
        $this->justification = $requirement->justification ?? '';
        $this->requirementText = $requirement->description ?? '';
    }

    public function cancel(): void
    {
        $this->decidingId = null;
    }

    public function saveDecision(ManageRequirements $requirements): void
    {
        $this->authorize('update', $this->framework);

        if ($this->decidingId === null) {
            return;
        }

        $this->validate([
            'applicability' => ['nullable', 'in:yes,no'],
            'justification' => ['nullable', 'string', 'max:5000', 'required_if:applicability,no'],
            'requirementText' => ['nullable', 'string', 'max:10000'],
        ]);

        $requirements->decide(
            $this->requirement($this->decidingId),
            match ($this->applicability) {
                'yes' => true,
                'no' => false,
                default => null,
            },
            $this->justification,
            $this->requirementText,
            $this->actor(),
        );
        $this->decidingId = null;
    }

    public function addRequirement(ManageRequirements $requirements): void
    {
        $this->authorize('update', $this->framework);
        $this->validate([
            'newReference' => ['required', 'string', 'max:64'],
            'newTitle' => ['required', 'string', 'max:255'],
            'newGroup' => ['nullable', 'string', 'max:255'],
            'newDescription' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $requirements->add($this->framework, $this->newReference, $this->newTitle, $this->newGroup, $this->newDescription);
        } catch (InvalidArgumentException) {
            $this->addError('newReference', __('audit::compliance.errors.reference_taken'));

            return;
        }

        $this->reset('newReference', 'newTitle', 'newDescription');
    }

    public function toggleArchived(): void
    {
        $framework = $this->framework;
        $this->authorize('update', $framework);
        $framework->forceFill(['is_active' => ! $framework->is_active])->save();
        unset($this->framework);
    }

    public function render(Coverage $coverage): View
    {
        $framework = $this->framework;
        $this->authorize('view', $framework);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->search, 0, 200))).'%';
        $state = RequirementState::tryFrom($this->state);

        $requirements = Requirement::query()
            ->with('controls')
            ->where('framework_id', $framework->id)
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('reference', $like)->orWhereLike('title', $like)))
            ->orderBy('position')
            ->get()
            ->when($state !== null, fn ($all) => $all->filter(fn (Requirement $requirement): bool => $requirement->state() === $state));

        return view('audit::livewire.frameworks.show', [
            'framework' => $framework,
            'groups' => $requirements->groupBy(fn (Requirement $requirement): string => $requirement->group ?? ''),
            'coverage' => $coverage->of($framework),
            'states' => RequirementState::cases(),
        ])->title($framework->name);
    }

    private function requirement(string $id): Requirement
    {
        return Requirement::query()->where('framework_id', $this->frameworkId)->findOrFail($id);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
