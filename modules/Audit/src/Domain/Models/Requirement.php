<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Audit\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Modules\Audit\Domain\Enums\RequirementState;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;

/**
 * A requirement of a framework (an Annex A control, an article), whether it
 * applies to the organization and why, and the controls that meet it.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $framework_id
 * @property string $reference
 * @property string $title
 * @property ?string $description
 * @property ?string $group
 * @property int $position
 * @property ?bool $is_applicable
 * @property ?string $justification
 * @property ?Carbon $updated_at
 * @property-read Framework $framework
 */
#[Table('audit_requirements')]
final class Requirement extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;

    public function state(): RequirementState
    {
        return RequirementState::of($this->is_applicable, array_values($this->controls->map(fn (Control $control) => $control->status)->all()));
    }

    /**
     * @return BelongsTo<Framework, $this>
     */
    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }

    /**
     * @return BelongsToMany<Control, $this>
     */
    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'audit_control_requirement', 'requirement_id', 'control_id')->orderBy('number');
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_applicable' => 'boolean',
        ];
    }
}
