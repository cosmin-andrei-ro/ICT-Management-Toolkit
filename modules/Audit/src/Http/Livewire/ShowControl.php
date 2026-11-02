<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Audit\Application\Actions\MapControl;
use Modules\Audit\Application\Actions\SaveControl;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Models\Control;
use Modules\Audit\Domain\Models\Framework;
use Modules\Audit\Domain\Models\Requirement;
use Modules\Core\Domain\Models\User;

/**
 * One control: how far it is implemented (kept by its owner) and the
 * requirements of every framework it helps to meet.
 *
 * @property-read Control $control Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowControl extends Component
{
    #[Locked]
    public string $controlId = '';

    public string $status = '';

    public string $notes = '';

    public bool $implementationSaved = false;

    public string $mapFrameworkId = '';

    public string $mapSearch = '';

    public function mount(Control $control): void
    {
        $this->authorize('view', $control);
        $this->controlId = $control->id;
        $this->status = $control->status->value;
        $this->notes = $control->implementation_notes ?? '';
    }

    #[Computed]
    public function control(): Control
    {
        return Control::query()->with(['owner', 'requirements.framework'])->findOrFail($this->controlId);
    }

    public function saveImplementation(SaveControl $save): void
    {
        $this->authorize('implement', $this->control);
        $this->validate([
            'status' => ['required', Rule::enum(ControlStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $save->implementation($this->control, ControlStatus::from($this->status), $this->notes);
        unset($this->control);
        $this->implementationSaved = true;
    }

    public function mapRequirement(string $requirementId, MapControl $map): void
    {
        $this->authorize('update', $this->control);

        try {
            $map->add($this->control, $requirementId, $this->actor());
        } catch (InvalidArgumentException) {
            $this->addError('mapSearch', __('audit::compliance.errors.invalid'));

            return;
        }

        unset($this->control);
    }

    public function unmapRequirement(string $requirementId, MapControl $map): void
    {
        $this->authorize('update', $this->control);
        $map->remove($this->control, $requirementId, $this->actor());
        unset($this->control);
    }

    public function delete(): void
    {
        $control = $this->control;
        $this->authorize('delete', $control);
        $control->delete();
        $this->redirectRoute('audit.controls.index');
    }

    public function render(): View
    {
        $control = $this->control;
        $this->authorize('view', $control);
        $canMap = auth()->user()?->can('update', $control) === true;
        $candidates = collect();

        if ($canMap && $this->mapFrameworkId !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim(mb_substr($this->mapSearch, 0, 200))).'%';
            $candidates = Requirement::query()
                ->where('framework_id', $this->mapFrameworkId)
                ->whereNotIn('id', $control->requirements->modelKeys())
                ->when(trim($this->mapSearch) !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->whereLike('reference', $like)->orWhereLike('title', $like)))
                ->orderBy('position')
                ->limit(20)
                ->get();
        }

        return view('audit::livewire.controls.show', [
            'control' => $control,
            'mapped' => $control->requirements->sortBy([['framework.name', 'asc'], ['position', 'asc']])->groupBy(fn (Requirement $requirement): string => $requirement->framework->name),
            'frameworks' => $canMap ? Framework::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'candidates' => $candidates,
            'statuses' => ControlStatus::cases(),
        ])->title($control->code().' · '.$control->name);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
