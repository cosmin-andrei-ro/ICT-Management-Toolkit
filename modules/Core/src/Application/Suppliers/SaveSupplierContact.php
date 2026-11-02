<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Application\Suppliers;

use InvalidArgumentException;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\SupplierContact;

/**
 * Adds or updates a contact of a supplier. The contact always belongs to the
 * supplier given, whatever the caller sends.
 */
final readonly class SaveSupplierContact
{
    /**
     * @param  array{name: string, role?: ?string, email?: ?string, phone?: ?string, notes?: ?string}  $fields
     */
    public function handle(Supplier $supplier, ?SupplierContact $contact, array $fields): SupplierContact
    {
        $contact ??= new SupplierContact;

        if ($contact->exists && $contact->supplier_id !== $supplier->id) {
            throw new InvalidArgumentException('The contact belongs to another supplier.');
        }

        $contact->forceFill([
            'organization_id' => $supplier->organization_id,
            'supplier_id' => $supplier->id,
            'name' => trim($fields['name']),
            'role' => self::blankToNull($fields['role'] ?? null),
            'email' => self::blankToNull($fields['email'] ?? null),
            'phone' => self::blankToNull($fields['phone'] ?? null),
            'notes' => self::blankToNull($fields['notes'] ?? null),
        ])->save();

        return $contact;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
