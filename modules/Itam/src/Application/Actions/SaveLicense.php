<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Enums\LicenseModel;
use Modules\Itam\Domain\Exceptions\LicenseSeatsInUse;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\SoftwareProduct;
use SensitiveParameter;

/**
 * Creates or updates a licence. The product is chosen or named (a new name
 * creates it). The key is kept unless a new one is given or it is removed; the
 * audit trail records that it changed, never its value. A licence with seats
 * cannot switch between people and devices.
 */
final readonly class SaveLicense
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private AuditTrail $trail,
    ) {}

    /**
     * @param  array{name: string, model: string, quantity?: int|string|null, supplier_id?: ?string, contract_id?: ?string, purchase_date?: ?string, cost?: ?string, currency?: ?string, starts_on?: ?string, expires_on?: ?string, notes?: ?string}  $fields
     * @param  ?string  $newKey  A new key; null keeps the current one.
     *
     * @throws InvalidArgumentException for references outside the organization
     * @throws LicenseSeatsInUse when switching between people and devices with seats in use
     */
    public function handle(?License $license, string $productId, ?string $productName, array $fields, #[SensitiveParameter] ?string $newKey = null, bool $removeKey = false): License
    {
        $organizationId = $this->context->requireId();
        $supplierId = self::blankToNull($fields['supplier_id'] ?? null);
        $contractId = self::blankToNull($fields['contract_id'] ?? null);

        // Scoped lookups: records of other organizations are not found.
        if (($supplierId !== null && ! Supplier::query()->whereKey($supplierId)->exists())
            || ($contractId !== null && ! Contract::query()->whereKey($contractId)->exists())) {
            throw new InvalidArgumentException('Supplier and contract must belong to the organization.');
        }

        $model = LicenseModel::from($fields['model']);

        if ($license !== null && $license->model->forDevices() !== $model->forDevices() && $license->assignments()->exists()) {
            throw new LicenseSeatsInUse('Release the seats before switching between people and devices.');
        }

        return $this->db->transaction(function () use ($license, $productId, $productName, $fields, $newKey, $removeKey, $organizationId, $supplierId, $contractId, $model): License {
            $product = $this->product($productId, $productName, $organizationId);
            $cost = self::blankToNull($fields['cost'] ?? null);
            $quantity = $fields['quantity'] ?? null;

            $license ??= new License;
            $license->forceFill([
                'product_id' => $product->id,
                'name' => trim($fields['name']),
                'model' => $model,
                'quantity' => $model->isCounted() ? max(1, (int) $quantity) : null,
                'supplier_id' => $supplierId,
                'contract_id' => $contractId,
                'purchase_date' => self::blankToNull($fields['purchase_date'] ?? null),
                'cost' => $cost,
                'currency' => $cost !== null ? strtoupper((string) self::blankToNull($fields['currency'] ?? null)) : null,
                'starts_on' => self::blankToNull($fields['starts_on'] ?? null),
                'expires_on' => self::blankToNull($fields['expires_on'] ?? null),
                'notes' => self::blankToNull($fields['notes'] ?? null),
            ]);

            $keyChange = null;
            $newKey = self::blankToNull($newKey);

            if ($newKey !== null) {
                $license->license_key = $newKey;
                $keyChange = 'changed';
            } elseif ($removeKey && $license->hasKey()) {
                $license->license_key = null;
                $keyChange = 'removed';
            }

            $license->save();

            // The key is hidden from the audit trail; that it changed is not.
            if ($keyChange !== null) {
                $this->trail->record(AuditEvent::Updated, $license, null, ['license_key' => $keyChange]);
            }

            return $license;
        });
    }

    private function product(string $productId, ?string $productName, string $organizationId): SoftwareProduct
    {
        $name = self::blankToNull($productName);

        if ($name === null) {
            return SoftwareProduct::query()->find($productId) ?? throw new InvalidArgumentException('Unknown product.');
        }

        $existing = SoftwareProduct::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        if ($existing !== null) {
            return $existing;
        }

        $product = new SoftwareProduct;
        $product->forceFill(['organization_id' => $organizationId, 'name' => mb_substr($name, 0, 255)])->save();

        return $product;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
