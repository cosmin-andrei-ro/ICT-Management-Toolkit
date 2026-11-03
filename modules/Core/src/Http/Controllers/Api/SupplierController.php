<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\SupplierContact;
use Modules\Core\Http\Api\ApiQuery;

/**
 * Suppliers and contracts, read-only (ADR 0065).
 */
final readonly class SupplierController
{
    public function __construct(private Gate $gate) {}

    /**
     * List suppliers. Filters: `filter[search]`, `filter[active]` (true/false). Sort: `name`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Supplier::class);

        return ApiQuery::collection(ApiQuery::paginate($request, Supplier::query(), [
            'search' => fn (Builder $query, string $value) => $query->where('name', 'ilike', self::like($value)),
            'active' => fn (Builder $query, string $value) => $query->where('is_active', $value === 'true'),
        ], ['name' => 'name'], 'name'), fn (Supplier $supplier): array => self::supplier($supplier));
    }

    /**
     * Show a supplier and its contacts.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        $this->gate->authorize('view', $supplier);

        return response()->json(['data' => [...self::supplier($supplier), 'contacts' => $supplier->contacts()->orderBy('name')->get()->map(fn (SupplierContact $contact): array => [
            'id' => $contact->id, 'name' => $contact->name, 'role' => $contact->role, 'email' => $contact->email, 'phone' => $contact->phone,
        ])->all()]]);
    }

    /**
     * List contracts. Filters: `filter[supplier_id]`, `filter[type]`, `filter[ends_before]` (Y-m-d), `filter[search]` (title or reference).
     * Sorts: `end_date`, `title`, `start_date`; by end date by default.
     */
    public function contracts(Request $request): AnonymousResourceCollection
    {
        $this->gate->authorize('viewAny', Contract::class);

        return ApiQuery::collection(ApiQuery::paginate($request, Contract::query()->with('supplier:id,name'), [
            'supplier_id' => fn (Builder $query, string $value) => $query->where('supplier_id', Str::isUuid($value) ? $value : null),
            'type' => fn (Builder $query, string $value) => $query->where('type', $value),
            'ends_before' => fn (Builder $query, string $value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $query->whereNotNull('end_date')->where('end_date', '<', $value) : $query->whereRaw('false'),
            'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query->where('title', 'ilike', self::like($value))->orWhere('reference', 'ilike', self::like($value))),
        ], ['end_date' => 'end_date', 'title' => 'title', 'start_date' => 'start_date'], 'end_date'), fn (Contract $contract): array => [
            'id' => $contract->id, 'supplier' => ['id' => $contract->supplier->id, 'name' => $contract->supplier->name],
            'title' => $contract->title, 'reference' => $contract->reference, 'type' => $contract->type->value,
            'start_date' => $contract->start_date->toDateString(), 'end_date' => $contract->end_date?->toDateString(), 'auto_renews' => $contract->auto_renews,
            'renewal_months' => $contract->renewal_months, 'notice_days' => $contract->notice_days, 'terminated_on' => $contract->terminated_on?->toDateString(),
            'value' => $contract->value, 'currency' => $contract->currency, 'billing_period' => $contract->billing_period?->value, 'owner_id' => $contract->owner_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function supplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id, 'name' => $supplier->name, 'legal_name' => $supplier->legal_name, 'tax_id' => $supplier->tax_id, 'customer_number' => $supplier->customer_number,
            'website' => $supplier->website, 'support_email' => $supplier->support_email, 'support_phone' => $supplier->support_phone, 'support_hours' => $supplier->support_hours,
            'is_active' => $supplier->is_active,
        ];
    }

    private static function like(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }
}
