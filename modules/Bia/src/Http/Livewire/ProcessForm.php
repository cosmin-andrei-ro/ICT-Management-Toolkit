<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

#[Layout('core::components.layouts.app')]
final class ProcessForm extends Component
{
    #[Locked]
    public ?string $processId = null;

    public string $name = '';

    public string $departmentId = '';

    public string $ownerId = '';

    public string $deputyId = '';

    public string $description = '';

    public string $peakPeriods = '';

    public string $status = 'active';

    public function mount(?BusinessProcess $process = null): void
    {
        if ($process === null || ! $process->exists) {
            $this->authorize('create', BusinessProcess::class);
            $name = request()->query('name');
            $this->name = is_string($name) ? mb_substr($name, 0, 255) : '';

            return;
        }

        $this->authorize('update', $process);
        $this->processId = $process->id;
        $this->name = $process->name;
        $this->departmentId = $process->department_id ?? '';
        $this->ownerId = $process->owner_id ?? '';
        $this->deputyId = $process->deputy_id ?? '';
        $this->description = $process->description ?? '';
        $this->peakPeriods = $process->peak_periods ?? '';
        $this->status = $process->status;
    }

    public function save(OrganizationContext $context): void
    {
        $process = $this->processId !== null ? BusinessProcess::query()->findOrFail($this->processId) : null;
        $this->authorize($process !== null ? 'update' : 'create', $process ?? BusinessProcess::class);
        $members = $this->members($context)->pluck('id')->all();

        $this->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueName(...)],
            // Scoped: departments of other organizations are not found.
            'departmentId' => ['nullable', 'uuid', Rule::in(Department::query()->pluck('id')->all())],
            'ownerId' => ['required', 'uuid', Rule::in($members)],
            'deputyId' => ['nullable', 'uuid', Rule::in($members), 'different:ownerId'],
            'description' => ['nullable', 'string', 'max:5000'],
            'peakPeriods' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'retired'])],
        ]);

        $process ??= new BusinessProcess;
        $process->fill([
            'name' => trim($this->name),
            'department_id' => $this->departmentId !== '' ? $this->departmentId : null,
            'owner_id' => $this->ownerId,
            'deputy_id' => $this->deputyId !== '' ? $this->deputyId : null,
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'peak_periods' => trim($this->peakPeriods) !== '' ? trim($this->peakPeriods) : null,
            'status' => $this->status,
        ])->save();

        $this->redirectRoute('bia.processes.show', $process);
    }

    /**
     * Case-insensitive, like the database's unique index.
     */
    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = is_string($value) && BusinessProcess::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
            ->when($this->processId !== null, fn ($query) => $query->whereKeyNot($this->processId))
            ->exists();

        if ($taken) {
            $fail(__('bia::processes.ui.name_taken'));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function members(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }

    public function render(OrganizationContext $context): View
    {
        return view('bia::livewire.processes.form', [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'people' => $this->members($context),
        ])->title(__($this->processId !== null ? 'bia::processes.ui.edit' : 'bia::processes.ui.new'));
    }
}
