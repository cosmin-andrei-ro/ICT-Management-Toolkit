<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Import;

use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Modules\Core\Application\Imports\ImportHasErrors;
use Modules\Core\Application\Imports\ImportRow;
use Modules\Core\Domain\Models\Supplier;
use Modules\Itam\Application\Actions\SaveLicense;
use Modules\Itam\Domain\Enums\LicenseModel;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\SoftwareProduct;

/**
 * Licences from a spreadsheet: one row per licence, matched by product and
 * licence name (update), or new (create); a new product name creates the
 * product. Keys are not imported: a spreadsheet of keys is exactly what must
 * not travel around. An empty cell keeps the current value. analyse() saves
 * nothing; apply() re-checks and saves every row, or none.
 */
final readonly class LicenseImport
{
    public const array COLUMNS = ['product', 'publisher', 'name', 'model', 'quantity', 'supplier', 'purchase_date', 'cost', 'currency', 'starts_on', 'expires_on', 'notes'];

    public function __construct(
        private ConnectionInterface $db,
        private SaveLicense $saveLicense,
    ) {}

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<ImportRow>
     */
    public function analyse(array $rows): array
    {
        return array_map(fn (array $planned): ImportRow => $planned['row'], $this->plan($rows));
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return int Rows saved.
     *
     * @throws ImportHasErrors when any row is invalid: nothing is saved
     */
    public function apply(array $rows): int
    {
        $plan = $this->plan($rows);

        if (array_filter($plan, fn (array $planned): bool => $planned['row']->action === ImportRow::ERROR) !== []) {
            throw new ImportHasErrors;
        }

        return $this->db->transaction(function () use ($plan): int {
            foreach ($plan as $planned) {
                $license = $this->saveLicense->handle($planned['license'], $planned['product_id'], $planned['product_name'], $planned['fields']);

                if ($planned['publisher'] !== null && $license->product->publisher !== $planned['publisher']) {
                    $license->product->update(['publisher' => $planned['publisher']]);
                }
            }

            return count($plan);
        });
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<array{row: ImportRow, license: ?License, product_id: string, product_name: ?string, publisher: ?string, fields: array{name: string, model: string, quantity: ?string, supplier_id: ?string, contract_id: ?string, purchase_date: ?string, cost: ?string, currency: ?string, starts_on: ?string, expires_on: ?string, notes: ?string}}>
     */
    private function plan(array $rows): array
    {
        $products = SoftwareProduct::query()->get()->keyBy(fn (SoftwareProduct $product): string => mb_strtolower($product->name));
        $licenses = License::query()->get()->keyBy(fn (License $license): string => $license->product_id.'|'.mb_strtolower($license->name));
        $suppliers = Supplier::query()->get()->keyBy(fn (Supplier $supplier): string => mb_strtolower($supplier->name));
        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $values = array_map(trim(...), $row['values']);
            $errors = [];
            $productName = $values['product'] ?? '';
            $name = $values['name'] ?? '';
            $product = $products->get(mb_strtolower($productName));
            $license = $product !== null ? $licenses->get($product->id.'|'.mb_strtolower($name)) : null;

            if ($productName === '' || mb_strlen($productName) > 255) {
                $errors[] = ImportRow::message('itam::import.licenses.errors.product');
            }

            if ($name === '' || mb_strlen($name) > 255) {
                $errors[] = ImportRow::message('itam::import.licenses.errors.name');
            }

            $key = mb_strtolower($productName.'|'.$name);

            if (isset($seen[$key])) {
                $errors[] = ImportRow::message('itam::import.licenses.errors.duplicate', ['line' => $seen[$key]]);
            }

            $seen[$key] ??= $row['line'];

            $modelValue = mb_strtolower($values['model'] ?? '');
            $model = $modelValue === '' ? $license?->model : collect(LicenseModel::cases())
                ->first(fn (LicenseModel $case): bool => $case->value === $modelValue || mb_strtolower($case->label()) === $modelValue);

            if ($model === null) {
                $errors[] = ImportRow::message('itam::import.licenses.errors.model', ['value' => $modelValue]);
            } elseif ($license !== null && $license->model->forDevices() !== $model->forDevices() && $license->assignments()->exists()) {
                $errors[] = ImportRow::message('itam::licenses.ui.model_has_seats');
            }

            $quantity = ($values['quantity'] ?? '') !== '' ? $values['quantity'] : ($license?->quantity !== null ? (string) $license->quantity : null);

            if ($model !== null && $model->isCounted() && ($quantity === null || ! ctype_digit($quantity) || (int) $quantity < 1)) {
                $errors[] = ImportRow::message('itam::import.licenses.errors.quantity');
            }

            $supplierName = mb_strtolower($values['supplier'] ?? '');
            $supplierId = $supplierName !== '' ? $suppliers->get($supplierName)?->id : $license?->supplier_id;

            if ($supplierName !== '' && $supplierId === null) {
                $errors[] = ImportRow::message('itam::import.errors.supplier', ['value' => $supplierName]);
            }

            $fields = [
                'name' => $name,
                'model' => $model->value ?? LicenseModel::PerUser->value,
                'quantity' => $quantity,
                'supplier_id' => $supplierId,
                'contract_id' => $license?->contract_id,
                'notes' => ($values['notes'] ?? '') !== '' ? $values['notes'] : $license?->notes,
            ];

            $date = function (string $column) use ($values, $license, &$errors): ?string {
                $value = $values[$column] ?? '';

                if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                    $errors[] = ImportRow::message('itam::import.errors.date', ['column' => $column, 'value' => $value]);
                }

                $current = $license?->getAttribute($column);

                return $value !== '' ? $value : ($current instanceof DateTimeInterface ? $current->format('Y-m-d') : null);
            };

            $fields['purchase_date'] = $date('purchase_date');
            $fields['starts_on'] = $date('starts_on');
            $fields['expires_on'] = $date('expires_on');

            $cost = str_replace(',', '.', $values['cost'] ?? '');

            if ($cost !== '' && ! preg_match('/^\d{1,12}(\.\d{1,2})?$/', $cost)) {
                $errors[] = ImportRow::message('itam::import.errors.cost', ['value' => $cost]);
            }

            $fields['cost'] = $cost !== '' ? $cost : $license?->cost;
            $fields['currency'] = ($values['currency'] ?? '') !== '' ? $values['currency'] : $license?->currency;

            if ($fields['cost'] !== null && ! preg_match('/^[A-Za-z]{3}$/', (string) $fields['currency'])) {
                $errors[] = ImportRow::message('itam::import.errors.currency');
            }

            $result[] = [
                'row' => new ImportRow($row['line'], trim($productName.' · '.$name, ' ·'), match (true) {
                    $errors !== [] => ImportRow::ERROR,
                    $license === null => ImportRow::CREATE,
                    default => ImportRow::UPDATE,
                }, $errors),
                'license' => $license,
                'product_id' => $product->id ?? '',
                'product_name' => $product === null ? $productName : null,
                'publisher' => ($values['publisher'] ?? '') !== '' ? $values['publisher'] : null,
                'fields' => $fields,
            ];
        }

        return $result;
    }
}
