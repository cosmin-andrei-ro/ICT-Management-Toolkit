<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bia\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Bia\Domain\Enums\DependencyKind;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;

/**
 * Something a process needs besides IT systems, in one assessment version.
 * Written by ManageDependencies only, which audits it on the assessment.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $assessment_id
 * @property DependencyKind $kind
 * @property ?string $supplier_id
 * @property ?string $location_id
 * @property ?string $upstream_process_id
 * @property ?string $user_id
 * @property ?string $role_name
 * @property ?int $minimum_people
 * @property ?string $note
 * @property ?Carbon $created_at
 * @property-read ?Supplier $supplier
 * @property-read ?Location $location
 * @property-read ?BusinessProcess $upstream
 * @property-read ?User $person
 */
#[Table('bia_dependencies')]
final class Dependency extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public const ?string UPDATED_AT = null;

    /**
     * What it is, in a few words, for lists and the audit trail.
     */
    public function label(): string
    {
        return match ($this->kind) {
            DependencyKind::Supplier => $this->supplier->name ?? '—',
            DependencyKind::Location => $this->location->name ?? '—',
            DependencyKind::Process => $this->upstream->name ?? '—',
            DependencyKind::Person => $this->person->name ?? '—',
            DependencyKind::Role => (string) $this->role_name,
        };
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /**
     * @return BelongsTo<BusinessProcess, $this>
     */
    public function upstream(): BelongsTo
    {
        return $this->belongsTo(BusinessProcess::class, 'upstream_process_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => DependencyKind::class,
            'minimum_people' => 'integer',
        ];
    }
}
