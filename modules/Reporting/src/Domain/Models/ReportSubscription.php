<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Reporting\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\User;
use Modules\Reporting\Database\Factories\ReportSubscriptionFactory;

/**
 * A person who receives the monthly management report.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property ?string $created_by
 * @property ?string $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[Table('reporting_report_subscriptions')]
#[UseFactory(ReportSubscriptionFactory::class)]
final class ReportSubscription extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<ReportSubscriptionFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
