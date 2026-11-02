<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Bcm\Domain\Enums\Phase;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Models\User;

/**
 * One step of a plan's runbook. Written by ManagePlanContent, which audits it
 * on the version.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $version_id
 * @property int $position
 * @property Phase $phase
 * @property string $action
 * @property ?string $responsible
 * @property ?string $responsible_user_id
 * @property ?int $estimated_minutes
 * @property ?string $configuration_item_id
 * @property ?string $notes
 * @property-read ?User $responsibleUser
 */
#[Table('bcm_steps')]
final class Step extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    protected function casts(): array
    {
        return [
            'phase' => Phase::class,
            'position' => 'integer',
            'estimated_minutes' => 'integer',
        ];
    }
}
