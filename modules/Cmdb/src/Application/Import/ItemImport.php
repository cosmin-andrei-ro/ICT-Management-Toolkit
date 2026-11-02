<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Import;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Cmdb\Application\Actions\SaveConfigurationItem;
use Modules\Cmdb\Domain\Enums\CiStatus;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Enums\Environment;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Application\Imports\ImportHasErrors;
use Modules\Core\Application\Imports\ImportRow;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Items from a spreadsheet: one row per item, matched by identifier (update) or
 * new (create). Columns: type, name, identifier, status, environment,
 * criticality, owner (email), team (name), description, and any attribute key
 * of the type. An empty cell keeps the current value of an existing item.
 * analyse() saves nothing; apply() re-checks and saves every row, or none.
 */
final readonly class ItemImport
{
    public const array COLUMNS = ['type', 'name', 'identifier', 'status', 'environment', 'criticality', 'owner', 'team', 'description'];

    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private ValidationFactory $validation,
        private SaveConfigurationItem $saveItem,
    ) {}

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<ImportRow>
     */
    public function analyse(array $rows): array
    {
        return array_map(fn (array $planned): ImportRow => $planned['row'], $this->plan($rows));
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<array{row: ImportRow, item: ?ConfigurationItem, type: ?CiType, fields: array{name: string, identifier: ?string, status: ?string, environment: ?string, criticality: ?string, owner_id: ?string, team_id: ?string, description: ?string}, properties: array<string, mixed>}>
     */
    private function plan(array $rows): array
    {
        $organizationId = $this->context->requireId();
        $types = CiType::query()->where('is_active', true)->get();
        $identifiers = array_values(array_filter(array_map(fn (array $row): string => mb_strtolower($row['values']['identifier'] ?? ''), $rows)));
        $existing = ConfigurationItem::query()->whereIn($this->db->raw('lower(identifier)'), $identifiers === [] ? [''] : $identifiers)->get()->keyBy(fn (ConfigurationItem $item): string => mb_strtolower((string) $item->identifier));
        $people = User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))->get()->keyBy(fn (User $user): string => mb_strtolower($user->email));
        $teams = Team::query()->get()->keyBy(fn (Team $team): string => mb_strtolower($team->name));
        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $values = $row['values'];
            $errors = [];
            $identifier = trim($values['identifier'] ?? '');
            $key = mb_strtolower($identifier);
            $item = $key !== '' ? $existing->get($key) : null;
            $label = trim(($values['name'] ?? '') !== '' ? $values['name'] : $identifier);

            if ($key !== '' && isset($seen[$key])) {
                $errors[] = ImportRow::message('cmdb::import.errors.duplicate_identifier', ['line' => $seen[$key]]);
            }

            if ($key !== '') {
                $seen[$key] = $row['line'];
            }

            $type = $this->type($types, $values['type'] ?? '') ?? $item?->type;

            if ($type === null) {
                $errors[] = ImportRow::message('cmdb::import.errors.type');
            } elseif ($item !== null && $item->type_id !== $type->id) {
                $errors[] = ImportRow::message('cmdb::import.errors.type_change');
            }

            $name = trim($values['name'] ?? '') !== '' ? trim($values['name']) : $item?->name;

            if ($name === null || $name === '' || mb_strlen($name) > 255) {
                $errors[] = ImportRow::message('cmdb::import.errors.name');
            }

            $fields = [
                'name' => (string) $name,
                'identifier' => $identifier !== '' ? $identifier : null,
                'status' => $this->enumValue(CiStatus::cases(), $values['status'] ?? '', $item?->status, 'status', $errors),
                'environment' => $this->enumValue(Environment::cases(), $values['environment'] ?? '', $item?->environment, 'environment', $errors),
                'criticality' => $this->enumValue(Criticality::cases(), $values['criticality'] ?? '', $item?->criticality, 'criticality', $errors),
                'owner_id' => $this->lookup($people, $values['owner'] ?? '', $item?->owner_id, 'owner', $errors),
                'team_id' => $this->lookup($teams, $values['team'] ?? '', $item?->team_id, 'team', $errors),
                'description' => trim($values['description'] ?? '') !== '' ? trim($values['description']) : $item?->description,
            ];

            $properties = $item !== null ? $item->properties : [];

            if ($type !== null) {
                foreach ($type->schema()->fields as $field) {
                    $cell = trim($values[$field->key] ?? '');

                    if ($cell !== '') {
                        $properties[$field->key] = $cell;
                    }
                }

                try {
                    $type->schema()->answer($properties, $this->validation);
                } catch (ValidationException $exception) {
                    foreach ($exception->validator->errors()->all() as $message) {
                        $errors[] = $message;
                    }
                }
            }

            $action = match (true) {
                $errors !== [] => ImportRow::ERROR,
                $item === null => ImportRow::CREATE,
                default => ImportRow::UPDATE,
            };

            $result[] = [
                'row' => new ImportRow($row['line'], $label, $action, $errors),
                'item' => $item,
                'type' => $type,
                'fields' => $fields,
                'properties' => $properties,
            ];
        }

        return $result;
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return int Rows saved.
     *
     * @throws ImportHasErrors when any row is invalid: nothing is saved
     */
    public function apply(array $rows): int
    {
        $plan = $this->plan($rows);

        if (array_filter($plan, fn (array $planned): bool => $planned['row']->action === ImportRow::ERROR) !== []) {
            throw new ImportHasErrors;
        }

        return $this->db->transaction(function () use ($plan): int {
            foreach ($plan as $planned) {
                if ($planned['type'] !== null) {
                    $this->saveItem->handle($planned['item'], $planned['type'], $planned['fields'], $planned['properties']);
                }
            }

            return count($plan);
        });
    }

    /**
     * @param  Collection<int, CiType>  $types
     */
    private function type(Collection $types, string $value): ?CiType
    {
        $value = mb_strtolower(trim($value));

        return $value === '' ? null : $types->first(fn (CiType $type): bool => $type->key === $value || mb_strtolower($type->name) === $value);
    }

    /**
     * Accepts the stored value ("in_use") or the label ("In use").
     *
     * @param  list<CiStatus>|list<Environment>|list<Criticality>  $cases
     * @param  list<string>  $errors
     */
    private function enumValue(array $cases, string $value, CiStatus|Environment|Criticality|null $current, string $column, array &$errors): ?string
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return $current?->value;
        }

        foreach ($cases as $case) {
            if ($case->value === $value || mb_strtolower($case->label()) === $value) {
                return $case->value;
            }
        }

        $errors[] = ImportRow::message('cmdb::import.errors.'.$column, ['value' => $value]);

        return $current?->value;
    }

    /**
     * @param  Collection<string, User>|Collection<string, Team>  $known
     * @param  list<string>  $errors
     */
    private function lookup(Collection $known, string $value, ?string $current, string $column, array &$errors): ?string
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return $current;
        }

        $match = $known->get($value);

        if ($match === null) {
            $errors[] = ImportRow::message('cmdb::import.errors.'.$column, ['value' => $value]);

            return $current;
        }

        return $match->id;
    }
}
