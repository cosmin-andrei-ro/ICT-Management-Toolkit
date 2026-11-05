<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\CustomFields;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Application\CustomFields\SaveCustomFields;
use Modules\Core\Domain\Concerns\HasCustomFields;
use Modules\Core\Domain\CustomFields\CustomFieldSchema;
use Modules\Core\Domain\CustomFields\CustomFieldValues;
use Modules\Core\Domain\Forms\FormField;
use Modules\Core\Domain\Models\User;

/**
 * The custom fields of a record, on its page. Read by whoever may view the record,
 * changed by whoever may change it; both checked on the record at each action.
 */
final class RecordCustomFields extends Component
{
    #[Locked]
    public string $type = '';

    #[Locked]
    public string $recordId = '';

    #[Locked]
    public string $ability = 'update';

    public bool $editing = false;

    /** @var array<string, mixed> */
    public array $answers = [];

    public function mount(Model $record, string $ability = 'update'): void
    {
        $this->type = $record->getMorphClass();
        $key = $record->getKey();
        $this->recordId = is_string($key) ? $key : '';
        $this->ability = $ability;
        $this->record();
    }

    public function edit(): void
    {
        $this->authorize($this->ability, $this->record());
        $this->answers = $this->stored();
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->resetValidation();
    }

    public function save(SaveCustomFields $save): void
    {
        $record = $this->record();
        $this->authorize($this->ability, $record);
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $this->resetValidation();
        $errors = [];

        try {
            $save->handle($record, $this->answers, $user);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $errors['answers.'.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        $this->editing = false;
    }

    public function render(): View
    {
        $record = $this->record();
        $fields = $this->fields();

        return view('core::livewire.custom-fields.record', [
            'fields' => $fields,
            'values' => $this->stored(),
            'canChange' => Gate::allows($this->ability, $record),
        ]);
    }

    /**
     * @return list<FormField>
     */
    private function fields(): array
    {
        return CustomFieldSchema::query()->where('entity_type', $this->type)->first()?->form()->fields ?? [];
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private function stored(): array
    {
        return CustomFieldValues::query()->where('entity_type', $this->type)->where('entity_id', $this->recordId)->first()->field_values ?? [];
    }

    private function record(): Model
    {
        $class = Relation::getMorphedModel($this->type);
        abort_unless(is_string($class) && is_subclass_of($class, Model::class) && in_array(HasCustomFields::class, class_uses_recursive($class), true), 404);
        $record = $class::query()->findOrFail($this->recordId);
        // A custom role can work on a ticket without the right to read every ticket.
        abort_unless(Gate::any(['view', $this->ability], $record), 403);

        return $record;
    }
}
