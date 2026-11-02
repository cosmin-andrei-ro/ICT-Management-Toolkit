<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\HasAttachments;
use Modules\Core\Domain\Concerns\TracksAuthors;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Itam\Application\Policies\LicensePolicy;
use Modules\Itam\Database\Factories\LicenseFactory;
use Modules\Itam\Domain\Enums\LicenseModel;

/**
 * Licences bought for a product: a number of seats for people or devices, or a
 * site licence. The key is encrypted at rest and hidden, so it is never in
 * serialized output, the audit trail or exports; RevealLicenseKey shows it.
 * Written through SaveLicense.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $product_id
 * @property string $name
 * @property LicenseModel $model
 * @property ?int $quantity
 * @property ?string $license_key
 * @property ?string $supplier_id
 * @property ?string $contract_id
 * @property ?CarbonImmutable $purchase_date
 * @property ?string $cost Decimal, as a string to keep it exact.
 * @property ?string $currency
 * @property ?CarbonImmutable $starts_on
 * @property ?CarbonImmutable $expires_on
 * @property ?CarbonImmutable $expiry_reminded_for
 * @property ?string $notes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read SoftwareProduct $product
 * @property-read ?int $assignments_count When loaded with withCount('assignments').
 * @property-read ?Supplier $supplier
 * @property-read ?Contract $contract
 */
#[Table('itam_licenses')]
#[UseFactory(LicenseFactory::class)]
#[UsePolicy(LicensePolicy::class)]
#[Fillable(['product_id', 'name', 'model', 'quantity', 'supplier_id', 'contract_id', 'purchase_date', 'cost', 'currency', 'starts_on', 'expires_on', 'notes'])]
#[Hidden(['license_key'])]
final class License extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /**
     * @return BelongsTo<SoftwareProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(SoftwareProduct::class, 'product_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
    }

    /**
     * @return HasMany<LicenseAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function hasKey(): bool
    {
        return $this->getRawOriginal('license_key') !== null;
    }

    public function isExpiredOn(CarbonImmutable $today): bool
    {
        return $this->expires_on !== null && $this->expires_on < $today->startOfDay();
    }

    /**
     * Licences still valid on that day: not expired (perpetual ones always are).
     *
     * @param  Builder<self>  $query
     */
    public function scopeValidOn(Builder $query, CarbonImmutable $today): void
    {
        $query->where(fn (Builder $inner) => $inner->whereNull('expires_on')->orWhere('expires_on', '>=', $today->toDateString()));
    }

    protected function casts(): array
    {
        return [
            'model' => LicenseModel::class,
            'quantity' => 'integer',
            'license_key' => 'encrypted',
            'purchase_date' => 'immutable_date',
            'starts_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'expiry_reminded_for' => 'immutable_date',
            'cost' => 'decimal:2',
        ];
    }
}
