<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Cmdb\Application\Actions;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Cmdb\Domain\Enums\CiStatus;
use Modules\Cmdb\Domain\Enums\Criticality;
use Modules\Cmdb\Domain\Enums\Environment;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Models\Team;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Creates or updates an item. Attribute values are validated against the type
 * (Core FormSchema); keys the type does not define are dropped.
 */
final readonly class SaveConfigurationItem
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private ValidationFactory $validation,
    ) {}

    /**
     * @param  array{name: string, identifier?: ?string, status?: ?string, environment?: ?string, criticality?: ?string, owner_id?: ?string, team_id?: ?string, description?: ?string, recovery_time_minutes?: ?int, recovery_point_minutes?: ?int}  $fields
     * @param  array<string, mixed>  $properties  Attribute values, keyed by attribute key.
     *
     * @throws \Illuminate\Validation\ValidationException keyed by attribute key
     * @throws InvalidArgumentException for a type, owner or team of another organization
     */
    public function handle(?ConfigurationItem $item, CiType $type, array $fields, array $properties): ConfigurationItem
    {
        $organizationId = $this->context->requireId();

        if ($type->organization_id !== $organizationId) {
            throw new InvalidArgumentException('The type belongs to another organization.');
        }

        $values = [];

        foreach ($type->schema()->answer($properties, $this->validation) as $answer) {
            if ($answer['value'] !== null && $answer['value'] !== '') {
                $values[$answer['key']] = $answer['value'];
            }
        }

        $ownerId = $this->blankToNull($fields['owner_id'] ?? null);
        $teamId = $this->blankToNull($fields['team_id'] ?? null);

        // Scoped lookups: people and teams of other organizations are refused.
        if ($ownerId !== null && ! User::query()->whereKey($ownerId)->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))->exists()) {
            throw new InvalidArgumentException('The owner must belong to the organization.');
        }

        if ($teamId !== null && ! Team::query()->whereKey($teamId)->exists()) {
            throw new InvalidArgumentException('The team must belong to the organization.');
        }

        return $this->db->transaction(function () use ($item, $type, $fields, $values, $ownerId, $teamId): ConfigurationItem {
            $item ??= new ConfigurationItem;
            $item->forceFill([
                'type_id' => $type->id,
                'name' => trim($fields['name']),
                'identifier' => $this->blankToNull($fields['identifier'] ?? null),
                'status' => CiStatus::from($this->blankToNull($fields['status'] ?? null) ?? CiStatus::InUse->value),
                'environment' => ($environment = $this->blankToNull($fields['environment'] ?? null)) !== null ? Environment::from($environment) : null,
                'criticality' => Criticality::from($this->blankToNull($fields['criticality'] ?? null) ?? Criticality::Medium->value),
                'owner_id' => $ownerId,
                'team_id' => $teamId,
                'description' => $this->blankToNull($fields['description'] ?? null),
                // Kept when the caller (an import) does not say.
                'recovery_time_minutes' => array_key_exists('recovery_time_minutes', $fields) ? $fields['recovery_time_minutes'] : $item->recovery_time_minutes,
                'recovery_point_minutes' => array_key_exists('recovery_point_minutes', $fields) ? $fields['recovery_point_minutes'] : $item->recovery_point_minutes,
                // An empty PHP array would be stored as a JSON list; the column holds an object.
                'properties' => $values === [] ? (object) [] : $values,
            ])->save();

            return $item;
        });
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
