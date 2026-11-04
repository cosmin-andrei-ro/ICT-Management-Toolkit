<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Ticketing\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * How long, in working minutes, a ticket may stay with a team (ADR 0074).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $team_id
 * @property int $minutes
 */
#[Table('ticketing_team_olas')]
#[Fillable(['team_id', 'minutes'])]
final class TeamOla extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    protected function casts(): array
    {
        return ['minutes' => 'integer'];
    }
}
