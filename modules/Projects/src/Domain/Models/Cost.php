<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Projects\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Supplier;
use Modules\Projects\Domain\Enums\CostCategory;

/**
 * Money spent on a project, in the project's currency.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $project_id
 * @property CostCategory $category
 * @property string $description
 * @property string $amount
 * @property Carbon $incurred_on
 * @property ?string $supplier_id
 * @property ?string $invoice_number
 * @property Carbon $created_at
 * @property-read ?Supplier $supplier
 */
#[Table('projects_costs')]
final class Cost extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CostCategory::class,
            'incurred_on' => 'date',
        ];
    }
}
