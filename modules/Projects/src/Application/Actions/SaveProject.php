<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Projects\Domain\Enums\PhaseKey;
use Modules\Projects\Domain\Enums\PhaseStatus;
use Modules\Projects\Domain\Models\Phase;
use Modules\Projects\Domain\Models\Project;
use RuntimeException;

/**
 * Creates a project with its four phases - initiation under way - and a
 * code of its own (PRJ-0001), or changes its details.
 */
final readonly class SaveProject
{
    public function __construct(private ConnectionInterface $db, private OrganizationContext $context) {}

    /**
     * @param  array{name: string, description: ?string, kind: \Modules\Projects\Domain\Enums\ProjectKind, sponsor_id: ?string, manager_id: ?string, planned_start: ?string, planned_end: ?string}  $data
     */
    public function handle(?Project $project, array $data): Project
    {
        foreach (['sponsor_id' => 'sponsorId', 'manager_id' => 'managerId'] as $column => $field) {
            if ($data[$column] !== null && ! self::isMember($data[$column], $this->context->requireId())) {
                throw ValidationException::withMessages([$field => __('projects::projects.errors.person')]);
            }
        }

        return $this->db->transaction(function () use ($project, $data): Project {
            $creating = $project === null;
            $project ??= new Project;
            $project->forceFill($data);

            if ($creating) {
                $project->forceFill(['code' => sprintf('PRJ-%04d', $this->next())]);
            }

            $project->save();

            if ($creating) {
                foreach (PhaseKey::cases() as $key) {
                    $phase = new Phase;
                    $phase->forceFill([
                        'project_id' => $project->id,
                        'key' => $key,
                        'position' => $key->position(),
                        'status' => $key === PhaseKey::Initiation ? PhaseStatus::Active : PhaseStatus::Pending,
                        'started_at' => $key === PhaseKey::Initiation ? now() : null,
                    ])->save();
                }
            }

            return $project;
        });
    }

    public static function isMember(string $userId, string $organizationId): bool
    {
        return User::query()->whereKey($userId)->where('is_active', true)
            ->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))
            ->exists();
    }

    private function next(): int
    {
        $row = $this->db->selectOne(<<<'SQL'
            INSERT INTO projects_sequences (organization_id, last_value) VALUES (?, 1)
            ON CONFLICT (organization_id) DO UPDATE SET last_value = projects_sequences.last_value + 1
            RETURNING last_value
            SQL, [$this->context->requireId()]);

        $value = is_object($row) ? ($row->last_value ?? null) : null;

        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new RuntimeException('Could not allocate a project code.');
        }

        return (int) $value;
    }
}
