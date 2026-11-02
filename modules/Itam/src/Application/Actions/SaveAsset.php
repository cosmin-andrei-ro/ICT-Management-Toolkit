<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Actions;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;
use Modules\Itam\Infrastructure\AssetTagGenerator;

/**
 * Creates an asset or updates its details. The status and the holder are not
 * details: they change through the life-cycle actions. A new asset starts as
 * ordered or in stock, with the next asset tag unless one is given.
 */
final readonly class SaveAsset
{
    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private ValidationFactory $validation,
        private AssetTagGenerator $tags,
    ) {}

    /**
     * @param  array{asset_tag?: ?string, name?: ?string, manufacturer?: ?string, model?: ?string, serial_number?: ?string, status?: ?string, location_id?: ?string, supplier_id?: ?string, contract_id?: ?string, purchase_date?: ?string, purchase_cost?: ?string, currency?: ?string, order_number?: ?string, invoice_number?: ?string, warranty_ends_on?: ?string, replace_on?: ?string, notes?: ?string}  $fields
     * @param  array<string, mixed>  $properties  Attribute values, keyed by attribute key.
     *
     * @throws \Illuminate\Validation\ValidationException keyed by attribute key
     * @throws InvalidArgumentException for a category, location, supplier or contract of another organization
     */
    public function handle(?Asset $asset, Category $category, array $fields, array $properties): Asset
    {
        $organizationId = $this->context->requireId();

        if ($category->organization_id !== $organizationId) {
            throw new InvalidArgumentException('The category belongs to another organization.');
        }

        $values = [];

        foreach ($category->schema()->answer($properties, $this->validation) as $answer) {
            if ($answer['value'] !== null && $answer['value'] !== '') {
                $values[$answer['key']] = $answer['value'];
            }
        }

        // Scoped lookups: records of other organizations are not found.
        $locationId = self::blankToNull($fields['location_id'] ?? null);
        $supplierId = self::blankToNull($fields['supplier_id'] ?? null);
        $contractId = self::blankToNull($fields['contract_id'] ?? null);

        if (($locationId !== null && ! Location::query()->whereKey($locationId)->exists())
            || ($supplierId !== null && ! Supplier::query()->whereKey($supplierId)->exists())
            || ($contractId !== null && ! Contract::query()->whereKey($contractId)->exists())) {
            throw new InvalidArgumentException('Location, supplier and contract must belong to the organization.');
        }

        $cost = self::blankToNull($fields['purchase_cost'] ?? null);

        return $this->db->transaction(function () use ($asset, $category, $fields, $values, $locationId, $supplierId, $contractId, $cost, $organizationId): Asset {
            $isNew = $asset === null;
            $asset ??= new Asset;
            $tag = self::blankToNull($fields['asset_tag'] ?? null);

            $asset->forceFill([
                'category_id' => $category->id,
                'asset_tag' => $tag ?? ($isNew ? $this->tags->next($organizationId) : $asset->asset_tag),
                'name' => self::blankToNull($fields['name'] ?? null),
                'manufacturer' => self::blankToNull($fields['manufacturer'] ?? null),
                'model' => self::blankToNull($fields['model'] ?? null),
                'serial_number' => self::blankToNull($fields['serial_number'] ?? null),
                'location_id' => $locationId,
                'supplier_id' => $supplierId,
                'contract_id' => $contractId,
                'purchase_date' => self::blankToNull($fields['purchase_date'] ?? null),
                'purchase_cost' => $cost,
                'currency' => $cost !== null ? strtoupper((string) self::blankToNull($fields['currency'] ?? null)) : null,
                'order_number' => self::blankToNull($fields['order_number'] ?? null),
                'invoice_number' => self::blankToNull($fields['invoice_number'] ?? null),
                'warranty_ends_on' => self::blankToNull($fields['warranty_ends_on'] ?? null),
                'replace_on' => self::blankToNull($fields['replace_on'] ?? null),
                'notes' => self::blankToNull($fields['notes'] ?? null),
                // An empty PHP array would be stored as a JSON list; the column holds an object.
                'properties' => $values === [] ? (object) [] : $values,
            ]);

            if ($isNew) {
                $asset->status = self::blankToNull($fields['status'] ?? null) === AssetStatus::Ordered->value ? AssetStatus::Ordered : AssetStatus::InStock;
            }

            $asset->save();

            return $asset;
        });
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
