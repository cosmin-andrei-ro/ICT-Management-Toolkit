<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Domain\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Itam\Application\Policies\AssetPolicy;
use Modules\Itam\Database\Factories\AssetFactory;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Enums\DisposalMethod;

/**
 * A piece of hardware the organization owns or leases. Its details are written
 * through SaveAsset; its status and holder change only through the life-cycle
 * actions (AssignAsset, ReturnAsset, ChangeAssetStatus, DisposeAsset).
 *
 * @property string $id
 * @property string $organization_id
 * @property string $category_id
 * @property string $asset_tag
 * @property ?string $name
 * @property ?string $manufacturer
 * @property ?string $model
 * @property ?string $serial_number
 * @property AssetStatus $status
 * @property ?string $location_id
 * @property ?string $assigned_to_id
 * @property ?string $supplier_id
 * @property ?string $contract_id
 * @property ?CarbonImmutable $purchase_date
 * @property ?string $purchase_cost Decimal, as a string to keep it exact.
 * @property ?string $currency
 * @property ?string $order_number
 * @property ?string $invoice_number
 * @property ?CarbonImmutable $warranty_ends_on
 * @property ?CarbonImmutable $warranty_reminded_for
 * @property ?CarbonImmutable $replace_on
 * @property array<string, string|int|float|bool|null> $properties Values of the category's attributes
 * @property ?string $notes
 * @property ?CarbonImmutable $disposed_on
 * @property ?DisposalMethod $disposal_method
 * @property ?bool $data_wiped
 * @property ?string $disposal_notes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read Category $category
 * @property-read ?Location $location
 * @property-read ?User $holder
 * @property-read ?Supplier $supplier
 * @property-read ?Contract $contract
 */
#[Table('itam_assets')]
#[UseFactory(AssetFactory::class)]
#[UsePolicy(AssetPolicy::class)]
#[Fillable(['category_id', 'asset_tag', 'name', 'manufacturer', 'model', 'serial_number', 'location_id', 'supplier_id', 'contract_id', 'purchase_date', 'purchase_cost', 'currency', 'order_number', 'invoice_number', 'warranty_ends_on', 'replace_on', 'notes'])]
final class Asset extends Model
{
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'in_stock',
        'properties' => '{}',
    ];

    /**
     * What people call it: its name, or else its make and model, or else its category.
     */
    public function displayName(): string
    {
        $makeAndModel = trim(($this->manufacturer ?? '').' '.($this->model ?? ''));

        return $this->name ?? ($makeAndModel !== '' ? $makeAndModel : $this->category->name);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
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
     * @return HasMany<AssetAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->latest('assigned_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $terms): void
    {
        $terms = trim($terms);

        if ($terms === '') {
            return;
        }

        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $terms).'%';
        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("search_vector @@ websearch_to_tsquery('simple', ?)", [$terms])
            ->orWhereRaw('asset_tag ILIKE ?', [$prefix])
            ->orWhereRaw('serial_number ILIKE ?', [$prefix]));
    }

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'disposal_method' => DisposalMethod::class,
            'purchase_date' => 'immutable_date',
            'warranty_ends_on' => 'immutable_date',
            'warranty_reminded_for' => 'immutable_date',
            'replace_on' => 'immutable_date',
            'disposed_on' => 'immutable_date',
            'purchase_cost' => 'decimal:2',
            'data_wiped' => 'boolean',
            'properties' => 'array',
        ];
    }
}
