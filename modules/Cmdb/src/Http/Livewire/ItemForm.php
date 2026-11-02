<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Cmdb\Application\Actions\ProvisionDefaultTypes;
use Modules\Cmdb\Application\Actions\SaveConfigurationItem;
use Modules\Cmdb\Domain\Enums\CiStatus;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Enums\Environment;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Domain\Time\Duration;

/**
 * New item, or editing one. The attribute fields follow the chosen type.
 */
#[Layout('core::components.layouts.app')]
final class ItemForm extends Component
{
    #[Locked]
    public ?string $itemId = null;

    public string $typeId = '';

    public string $name = '';

    public string $identifier = '';

    public string $status = 'in_use';

    public string $environment = '';

    public string $criticality = 'medium';

    public string $ownerId = '';

    public string $teamId = '';

    public string $description = '';

    public string $recoveryTimeValue = '';

    public string $recoveryTimeUnit = 'hours';

    public string $recoveryPointValue = '';

    public string $recoveryPointUnit = 'hours';

    /** @var array<string, mixed> Attribute values of the type, keyed by attribute key. */
    public array $properties = [];

    public function mount(ProvisionDefaultTypes $provision, ?ConfigurationItem $item = null): void
    {
        if ($item !== null && $item->exists) {
            $this->authorize('update', $item);
            $this->itemId = $item->id;
            $this->typeId = $item->type_id;
            $this->name = $item->name;
            $this->identifier = $item->identifier ?? '';
            $this->status = $item->status->value;
            $this->environment = $item->environment->value ?? '';
            $this->criticality = $item->criticality->value;
            $this->ownerId = $item->owner_id ?? '';
            $this->teamId = $item->team_id ?? '';
            $this->description = $item->description ?? '';
            $this->properties = $item->properties;
            ['value' => $this->recoveryTimeValue, 'unit' => $this->recoveryTimeUnit] = Duration::split($item->recovery_time_minutes);
            ['value' => $this->recoveryPointValue, 'unit' => $this->recoveryPointUnit] = Duration::split($item->recovery_point_minutes);

            return;
        }

        $this->authorize('create', ConfigurationItem::class);
        $provision->handle();
        $type = request()->query('type');
        $this->typeId = is_string($type) && CiType::query()->where('is_active', true)->whereKey($type)->exists() ? $type : '';
    }

    public function updatedTypeId(): void
    {
        // Values of another type's attributes would be dropped anyway.
        $this->properties = [];
        $this->resetErrorBag();
    }

    public function save(SaveConfigurationItem $saveItem): void
    {
        $item = $this->itemId !== null ? ConfigurationItem::query()->findOrFail($this->itemId) : null;
        $this->authorize($item !== null ? 'update' : 'create', $item ?? ConfigurationItem::class);

        $this->validate([
            'typeId' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255', $this->uniqueIdentifier(...)],
            'status' => ['required', Rule::enum(CiStatus::class)],
            'environment' => ['nullable', Rule::enum(Environment::class)],
            'criticality' => ['required', Rule::enum(Criticality::class)],
            'ownerId' => ['nullable', 'uuid'],
            'teamId' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:5000'],
            'recoveryTimeValue' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'recoveryPointValue' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'recoveryTimeUnit' => ['required', Rule::in(array_keys(Duration::UNITS))],
            'recoveryPointUnit' => ['required', Rule::in(array_keys(Duration::UNITS))],
        ]);

        // Scoped lookup: a type of another organization is not found.
        $type = CiType::query()->findOrFail($this->typeId);

        try {
            $item = $saveItem->handle($item, $type, [
                'name' => $this->name,
                'identifier' => $this->identifier,
                'status' => $this->status,
                'environment' => $this->environment,
                'criticality' => $this->criticality,
                'owner_id' => $this->ownerId,
                'team_id' => $this->teamId,
                'description' => $this->description,
                'recovery_time_minutes' => Duration::toMinutes($this->recoveryTimeValue, $this->recoveryTimeUnit),
                'recovery_point_minutes' => Duration::toMinutes($this->recoveryPointValue, $this->recoveryPointUnit),
            ], $this->properties);
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $key => $messages) {
                $errors['properties.'.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        } catch (InvalidArgumentException) {
            $this->addError('ownerId', __('cmdb::items.ui.invalid_owner_or_team'));

            return;
        }

        $this->redirectRoute('cmdb.items.show', $item);
    }

    /**
     * Case-insensitive, like the database's unique index: SRV-01 and srv-01 are the same item.
     */
    private function uniqueIdentifier(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = is_string($value) && trim($value) !== '' && ConfigurationItem::query()
            ->whereRaw('lower(identifier) = ?', [mb_strtolower(trim($value))])
            ->when($this->itemId !== null, fn ($query) => $query->whereKeyNot($this->itemId))
            ->exists();

        if ($taken) {
            $fail(__('cmdb::items.ui.identifier_taken'));
        }
    }

    public function render(OrganizationContext $context): View
    {
        $type = $this->typeId !== '' ? CiType::query()->find($this->typeId) : null;
        $organizationId = $context->id();

        return view('cmdb::livewire.form', [
            'types' => CiType::query()->where(fn ($query) => $query->where('is_active', true)->orWhereKey($this->typeId ?: null))->orderBy('name')->get(),
            'fields' => $type?->schema()->fields ?? [],
            'statuses' => CiStatus::cases(),
            'environments' => Environment::cases(),
            'criticalities' => Criticality::cases(),
            'people' => User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))->orderBy('name')->get(['id', 'name']),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'units' => array_keys(Duration::UNITS),
        ])->title(__($this->itemId !== null ? 'cmdb::items.ui.edit' : 'cmdb::items.ui.new'));
    }
}
