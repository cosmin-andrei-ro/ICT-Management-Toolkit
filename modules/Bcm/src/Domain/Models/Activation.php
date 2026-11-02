<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Bcm\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Bcm\Application\Policies\ActivationPolicy;
use Modules\Bcm\Domain\Enums\Scenario;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;

/**
 * A real activation of a plan: when and why it was activated, when the service
 * was back, and what was learned. Closed activations never change. Written
 * through the activation actions only.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $plan_id
 * @property string $version_id
 * @property ?Scenario $scenario
 * @property string $reason
 * @property Carbon $activated_at
 * @property ?string $activated_by
 * @property ?Carbon $restored_at
 * @property ?Carbon $closed_at
 * @property ?string $closed_by
 * @property ?int $rto_minutes Strictest RTO when the plan was activated.
 * @property ?string $summary
 * @property ?string $lessons
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Plan $plan
 * @property-read PlanVersion $version
 * @property-read ?User $activator
 * @property-read ?User $closer
 */
#[Table('bcm_activations')]
#[UsePolicy(ActivationPolicy::class)]
final class Activation extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Minutes from activation to service back; null until it is back.
     */
    public function recoveryMinutes(): ?int
    {
        return $this->restored_at !== null ? (int) $this->activated_at->diffInMinutes($this->restored_at) : null;
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PlanVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    protected function casts(): array
    {
        return [
            'scenario' => Scenario::class,
            'activated_at' => 'datetime',
            'restored_at' => 'datetime',
            'closed_at' => 'datetime',
            'rto_minutes' => 'integer',
        ];
    }
}
