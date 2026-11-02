<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Audit\Application\Actions\SaveControl;
use Modules\Audit\Domain\Enums\ControlStatus;
use Modules\Audit\Domain\Models\Control;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Registers a control or changes it: what it is, who owns it, how far it is implemented.
 */
#[Layout('core::components.layouts.app')]
final class ControlForm extends Component
{
    #[Locked]
    public ?string $controlId = null;

    public string $name = '';

    public string $description = '';

    public string $ownerId = '';

    public string $status = 'planned';

    public string $notes = '';

    /** Months between periodic tests; empty for none. */
    public string $testInterval = '';

    public function mount(?Control $control = null): void
    {
        if ($control === null || ! $control->exists) {
            $this->authorize('create', Control::class);

            return;
        }

        $this->authorize('update', $control);
        $this->controlId = $control->id;
        $this->name = $control->name;
        $this->description = $control->description ?? '';
        $this->ownerId = $control->owner_id ?? '';
        $this->status = $control->status->value;
        $this->notes = $control->implementation_notes ?? '';
        $this->testInterval = (string) ($control->test_interval_months ?? '');
    }

    public function save(SaveControl $save, OrganizationContext $context): void
    {
        $control = $this->controlId !== null ? Control::query()->findOrFail($this->controlId) : null;
        $this->authorize($control !== null ? 'update' : 'create', $control ?? Control::class);

        $this->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueName(...)],
            'description' => ['nullable', 'string', 'max:10000'],
            'ownerId' => ['required', 'uuid', Rule::in($this->people($context)->pluck('id')->all())],
            'status' => ['required', Rule::enum(ControlStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'testInterval' => ['nullable', 'integer', 'between:1,60'],
        ]);

        $saved = $save->handle($control, [
            'name' => $this->name,
            'description' => $this->description,
            'owner_id' => $this->ownerId,
            'status' => ControlStatus::from($this->status),
            'implementation_notes' => $this->notes,
            'test_interval_months' => $this->testInterval !== '' ? (int) $this->testInterval : null,
        ]);

        $this->redirectRoute('audit.controls.show', $saved);
    }

    public function render(OrganizationContext $context): View
    {
        return view('audit::livewire.controls.form', [
            'people' => $this->people($context),
            'statuses' => ControlStatus::cases(),
        ])->title(__($this->controlId !== null ? 'audit::compliance.controls.edit' : 'audit::compliance.controls.new'));
    }

    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        // Scoped to the organization; deleted controls free their name.
        $taken = is_string($value) && Control::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
            ->when($this->controlId !== null, fn ($query) => $query->whereKeyNot($this->controlId))
            ->exists();

        if ($taken) {
            $fail(__('audit::compliance.controls.name_taken'));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function people(OrganizationContext $context): Collection
    {
        return User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($context->requireId()))->orderBy('name')->get(['id', 'name']);
    }
}
