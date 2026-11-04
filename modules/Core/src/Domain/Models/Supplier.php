<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Application\Policies\SupplierPolicy;
use Modules\Core\Database\Factories\SupplierFactory;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\BelongsToOrganization;
use Modules\Core\Domain\Concerns\HasTags;
use Modules\Core\Domain\Concerns\Taggable;
use Modules\Core\Domain\Concerns\TracksAuthors;

/**
 * A company IT buys from or relies on: vendors, resellers, service providers.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property ?string $legal_name
 * @property ?string $tax_id
 * @property ?string $customer_number
 * @property ?string $website
 * @property ?string $support_email
 * @property ?string $support_phone
 * @property ?string $support_hours
 * @property ?string $notes
 * @property bool $is_active
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
#[Table('core_suppliers')]
#[UseFactory(SupplierFactory::class)]
#[UsePolicy(SupplierPolicy::class)]
#[Fillable(['name', 'legal_name', 'tax_id', 'customer_number', 'website', 'support_email', 'support_phone', 'support_hours', 'notes', 'is_active'])]
final class Supplier extends Model implements Taggable
{
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use HasTags;
    use HasUuids;
    use SoftDeletes;
    use TracksAuthors;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return HasMany<SupplierContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderBy('name');
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
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

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $terms).'%';
        $query->where(fn (Builder $inner) => $inner
            ->whereLike('name', $like)
            ->orWhereLike('legal_name', $like)
            ->orWhereLike('tax_id', $like));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
