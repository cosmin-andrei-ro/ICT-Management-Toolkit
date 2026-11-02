<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Contracts;

use InvalidArgumentException;
use Modules\Core\Domain\Enums\BillingPeriod;
use Modules\Core\Domain\Enums\ContractType;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

/**
 * Creates or updates a contract. Fields are validated by the caller; this keeps
 * the supplier and the owner inside the organization and drops values that make
 * no sense together (a renewal term without automatic renewal…).
 */
final readonly class SaveContract
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @param  array{supplier_id: string, title: string, reference?: ?string, type: string, start_date: string, end_date?: ?string, auto_renews?: bool, renewal_months?: int|string|null, notice_days?: int|string|null, terminated_on?: ?string, value?: ?string, currency?: ?string, billing_period?: ?string, owner_id?: ?string, description?: ?string}  $fields
     *
     * @throws InvalidArgumentException for a supplier or owner of another organization
     */
    public function handle(?Contract $contract, array $fields): Contract
    {
        $organizationId = $this->context->requireId();

        // Scoped lookup: a supplier of another organization is not found.
        $supplier = Supplier::query()->find($fields['supplier_id']);

        if ($supplier === null) {
            throw new InvalidArgumentException('The supplier must belong to the organization.');
        }

        $ownerId = self::blankToNull($fields['owner_id'] ?? null);

        if ($ownerId !== null && ! User::query()->whereKey($ownerId)->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))->exists()) {
            throw new InvalidArgumentException('The owner must belong to the organization.');
        }

        $endDate = self::blankToNull($fields['end_date'] ?? null);
        $autoRenews = ($fields['auto_renews'] ?? false) && $endDate !== null;
        $value = self::blankToNull($fields['value'] ?? null);
        $billing = self::blankToNull($fields['billing_period'] ?? null);

        $contract ??= new Contract;
        $contract->forceFill([
            'supplier_id' => $supplier->id,
            'title' => trim($fields['title']),
            'reference' => self::blankToNull($fields['reference'] ?? null),
            'type' => ContractType::from($fields['type']),
            'start_date' => $fields['start_date'],
            'end_date' => $endDate,
            'auto_renews' => $autoRenews,
            'renewal_months' => $autoRenews ? self::integerOrNull($fields['renewal_months'] ?? null) : null,
            'notice_days' => $endDate !== null ? self::integerOrNull($fields['notice_days'] ?? null) : null,
            'terminated_on' => self::blankToNull($fields['terminated_on'] ?? null),
            'value' => $value,
            'currency' => $value !== null ? strtoupper((string) self::blankToNull($fields['currency'] ?? null)) : null,
            'billing_period' => $billing !== null ? BillingPeriod::from($billing) : null,
            'owner_id' => $ownerId,
            'description' => self::blankToNull($fields['description'] ?? null),
        ])->save();

        return $contract;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private static function integerOrNull(int|string|null $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
