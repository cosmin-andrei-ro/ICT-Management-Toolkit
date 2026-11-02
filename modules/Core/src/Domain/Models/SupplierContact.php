<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SupplierContactFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * Someone to call at a supplier. Business details only (GDPR: data minimisation);
 * authorized through the supplier.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $supplier_id
 * @property string $name
 * @property ?string $role
 * @property ?string $email
 * @property ?string $phone
 * @property ?string $notes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Supplier $supplier
 */
#[Table('core_supplier_contacts')]
#[UseFactory(SupplierContactFactory::class)]
#[Fillable(['name', 'role', 'email', 'phone', 'notes'])]
final class SupplierContact extends Model
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<SupplierContactFactory> */
    use HasFactory;

    use HasUuids;
    use TracksAuthors;

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
}
