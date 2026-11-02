<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Applications\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Applications\Application\Actions\SaveApplication;
use Modules\Applications\Domain\Models\Application;
use Modules\Core\Domain\Models\Department;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * The application's identity and owners: the cover of the template.
 */
#[Layout('core::components.layouts.app')]
final class ApplicationForm extends Component
{
    #[Locked]
    public ?string $applicationId = null;

    public string $name = '';

    public string $code = '';

    public string $departmentId = '';

    public string $departmentEmail = '';

    public string $businessOwnerId = '';

    public string $technicalOwnerId = '';

    public string $deputyId = '';

    public function mount(?Application $application = null): void
    {
        if ($application === null || ! $application->exists) {
            $this->authorize('create', Application::class);

            return;
        }

        $this->authorize('update', $application);
        $this->applicationId = $application->id;
        $this->name = $application->name;
        $this->code = $application->code;
        $this->departmentId = $application->department_id ?? '';
        $this->departmentEmail = $application->department_email ?? '';
        $this->businessOwnerId = $application->business_owner_id ?? '';
        $this->technicalOwnerId = $application->technical_owner_id ?? '';
        $this->deputyId = $application->deputy_id ?? '';
    }

    public function save(SaveApplication $save, OrganizationContext $context): void
    {
        $application = $this->applicationId !== null ? Application::query()->findOrFail($this->applicationId) : null;
        $this->authorize($application !== null ? 'update' : 'create', $application ?? Application::class);
        $members = $this->members($context)->pluck('id')->all();

        $this->validate([
            'name' => ['required', 'string', 'max:255', $this->unique('lower', 'applications::applications.errors.name_taken')],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $this->unique('upper', 'applications::applications.errors.code_taken')],
            'departmentId' => ['nullable', 'uuid', Rule::in(Department::query()->pluck('id')->all())],
            'departmentEmail' => ['nullable', 'string', 'max:255', 'email'],
            'businessOwnerId' => ['required', 'uuid', Rule::in($members)],
            'technicalOwnerId' => ['required', 'uuid', Rule::in($members)],
            'deputyId' => ['nullable', 'uuid', Rule::in($members)],
        ]);

        $saved = $save->handle($application, [
            'code' => $this->code,
            'name' => $this->name,
            'department_id' => $this->departmentId !== '' ? $this->departmentId : null,
            'department_email' => trim($this->departmentEmail) !== '' ? trim($this->departmentEmail) : null,
            'business_owner_id' => $this->businessOwnerId,
            'technical_owner_id' => $this->technicalOwnerId,
            'deputy_id' => $this->deputyId !== '' ? $this->deputyId : null,
        ]);

        $this->redirectRoute('applications.show', $saved);
    }

    public function render(OrganizationContext $context): View
    {
        return view('applications::livewire.applications.form', [
            'people' => $this->members($context),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ])->title(__($this->applicationId !== null ? 'applications::applications.ui.edit' : 'applications::applications.ui.new'));
    }

    /**
     * @param  'upper'|'lower'  $function  upper for the code, lower for the name (as the unique indexes).
     */
    private function unique(string $function, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($function, $message): void {
            $normalized = is_string($value) ? ($function === 'upper' ? strtoupper(trim($value)) : mb_strtolower(trim($value))) : '';
            $taken = $normalized !== '' && Application::query()
                ->whereRaw($function === 'upper' ? 'upper(code) = ?' : 'lower(name) = ?', [$normalized])
                ->when($this->applicationId !== null, fn ($query) => $query->whereKeyNot($this->applicationId))
                ->exists();

            if ($taken) {
                $fail(__($message));
            }
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function members(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }
}
