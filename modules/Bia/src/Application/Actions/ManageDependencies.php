<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Application\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Bia\Domain\Enums\DependencyKind;
use Modules\Bia\Domain\Exceptions\AssessmentLocked;
use Modules\Bia\Domain\Models\Assessment;
use Modules\Bia\Domain\Models\BusinessProcess;
use Modules\Bia\Domain\Models\Dependency;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Adds and removes what a process needs, on an open assessment only: CMDB items
 * (through Core's ConfigurationItems contract, so the item page lists the
 * process), suppliers, locations, upstream processes, key roles and key people.
 * Every change is audited on the assessment.
 */
final readonly class ManageDependencies
{
    public function __construct(
        private ConfigurationItems $items,
        private AuditTrail $trail,
        private OrganizationContext $context,
    ) {}

    /**
     * @throws AssessmentLocked
     * @throws InvalidArgumentException for an item of another organization
     */
    public function linkItem(Assessment $assessment, string $itemId, User $actor): void
    {
        $this->assertOpen($assessment);
        $this->items->link($assessment, $itemId, $actor);
    }

    public function unlinkItem(Assessment $assessment, string $itemId, User $actor): void
    {
        $this->assertOpen($assessment);
        $this->items->unlink($assessment, $itemId, $actor);
    }

    /**
     * @param  array{supplier_id?: string, location_id?: string, upstream_process_id?: string, user_id?: string, role_name?: string, minimum_people?: ?int, note?: ?string}  $target
     *
     * @throws AssessmentLocked
     * @throws InvalidArgumentException for a target outside the organization, the process itself, or a duplicate
     */
    public function add(Assessment $assessment, DependencyKind $kind, array $target, User $actor): Dependency
    {
        $this->assertOpen($assessment);
        $values = $this->target($assessment, $kind, $target);

        $duplicate = Dependency::query()->where('assessment_id', $assessment->id)->where('kind', $kind->value)
            ->when($kind === DependencyKind::Role, fn ($query) => $query->whereRaw('lower(role_name) = ?', [mb_strtolower((string) $values['role_name'])]))
            ->when($kind !== DependencyKind::Role, fn ($query) => $query->where(array_filter($values, fn (mixed $value, string $column): bool => str_ends_with($column, '_id'), ARRAY_FILTER_USE_BOTH)))
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Already a dependency of this assessment.');
        }

        $dependency = new Dependency;
        $dependency->forceFill([
            'organization_id' => $assessment->organization_id,
            'assessment_id' => $assessment->id,
            'kind' => $kind,
            ...$values,
            'note' => isset($target['note']) && trim($target['note']) !== '' ? mb_substr(trim($target['note']), 0, 1000) : null,
            'created_by' => $actor->id,
        ])->save();

        $this->trail->record(AuditEvent::Updated, $assessment, null, ['dependency_added' => $kind->value.': '.$dependency->label()], $actor->id);

        return $dependency;
    }

    /**
     * @throws AssessmentLocked
     */
    public function remove(Dependency $dependency, User $actor): void
    {
        $assessment = Assessment::query()->findOrFail($dependency->assessment_id);
        $this->assertOpen($assessment);
        $label = $dependency->kind->value.': '.$dependency->label();
        $dependency->delete();

        $this->trail->record(AuditEvent::Updated, $assessment, ['dependency_removed' => $label], null, $actor->id);
    }

    /**
     * A new version starts with everything the previous one needed.
     */
    public function copy(Assessment $from, Assessment $to, User $actor): void
    {
        foreach ($this->items->linkedIds($from->getMorphClass(), [$from->id])[$from->id] ?? [] as $itemId) {
            $this->items->link($to, $itemId, $actor);
        }

        foreach (Dependency::query()->where('assessment_id', $from->id)->get() as $dependency) {
            $copy = $dependency->replicate(['id', 'assessment_id', 'created_at', 'created_by']);
            $copy->forceFill(['assessment_id' => $to->id, 'created_by' => $actor->id])->save();
        }
    }

    private function assertOpen(Assessment $assessment): void
    {
        if (! $assessment->status->isEditable()) {
            throw new AssessmentLocked;
        }
    }

    /**
     * @param  array{supplier_id?: string, location_id?: string, upstream_process_id?: string, user_id?: string, role_name?: string, minimum_people?: ?int}  $target
     * @return array<string, string|int|null>
     */
    private function target(Assessment $assessment, DependencyKind $kind, array $target): array
    {
        $id = match ($kind) {
            DependencyKind::Supplier => $target['supplier_id'] ?? '',
            DependencyKind::Location => $target['location_id'] ?? '',
            DependencyKind::Process => $target['upstream_process_id'] ?? '',
            DependencyKind::Person => $target['user_id'] ?? '',
            DependencyKind::Role => '',
        };

        $roleName = trim($target['role_name'] ?? '');

        // Nothing chosen (or garbage) must not reach a uuid column.
        if ($kind !== DependencyKind::Role && ! Str::isUuid($id)) {
            throw new InvalidArgumentException('Choose what to add.');
        }

        // Scoped lookups: records of other organizations are not found.
        $valid = match ($kind) {
            DependencyKind::Supplier => Supplier::query()->whereKey($id)->exists(),
            DependencyKind::Location => Location::query()->whereKey($id)->exists(),
            DependencyKind::Process => $id !== $assessment->process_id && BusinessProcess::query()->whereKey($id)->exists(),
            DependencyKind::Person => User::query()->whereKey($id)->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($this->context->requireId()))->exists(),
            DependencyKind::Role => $roleName !== '' && mb_strlen($roleName) <= 255,
        };

        if (! $valid) {
            throw new InvalidArgumentException('Not a valid dependency for this process.');
        }

        return match ($kind) {
            DependencyKind::Supplier => ['supplier_id' => $id],
            DependencyKind::Location => ['location_id' => $id],
            DependencyKind::Process => ['upstream_process_id' => $id],
            DependencyKind::Person => ['user_id' => $id],
            DependencyKind::Role => ['role_name' => $roleName, 'minimum_people' => $target['minimum_people'] ?? null],
        };
    }
}
