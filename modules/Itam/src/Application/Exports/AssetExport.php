<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Exports;

use Generator;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Exports\ExportSource;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Location;
use Modules\Itam\Application\Import\AssetImport;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;

/**
 * The whole asset register, one row per asset. The columns are those of the
 * import (with the holder's email), so a register can be exported, corrected in
 * a spreadsheet and imported back; status, disposal and contract columns follow
 * and are ignored by the import. Attribute columns of every category come last.
 */
final readonly class AssetExport implements ExportSource
{
    public const string TYPE = 'itam.assets';

    private const array EXTRA = ['contract', 'disposed_on', 'disposal_method', 'data_wiped'];

    public function sheets(Export $export): array
    {
        $attributes = $this->attributeKeys();

        return [
            new ExportSheet(__('itam::assets.title'), [...AssetImport::COLUMNS, ...self::EXTRA, ...$attributes], $this->rows($attributes)),
        ];
    }

    public function filename(Export $export): string
    {
        return 'assets-'.$export->created_at?->format('Y-m-d');
    }

    /**
     * @param  list<string>  $attributes
     * @return Generator<list<string|int|float|bool|null>>
     */
    private function rows(array $attributes): Generator
    {
        $locations = Location::paths(false);
        $query = Asset::query()->with(['category', 'holder', 'supplier', 'contract']);

        foreach ($query->lazyById(500) as $asset) {
            $row = [
                $asset->category->key,
                $asset->asset_tag,
                $asset->serial_number,
                $asset->name,
                $asset->manufacturer,
                $asset->model,
                $asset->status->value,
                $asset->holder?->email,
                $asset->location_id !== null ? str_replace(' › ', ' > ', $locations[$asset->location_id] ?? '') : null,
                $asset->supplier?->name,
                $asset->purchase_date?->toDateString(),
                $asset->purchase_cost,
                $asset->currency,
                $asset->order_number,
                $asset->invoice_number,
                $asset->warranty_ends_on?->toDateString(),
                $asset->replace_on?->toDateString(),
                $asset->notes,
                $asset->contract?->title,
                $asset->disposed_on?->toDateString(),
                $asset->disposal_method?->value,
                $asset->data_wiped === null ? null : ($asset->data_wiped ? 'yes' : 'no'),
            ];

            foreach ($attributes as $key) {
                $value = $asset->properties[$key] ?? null;
                $row[] = is_bool($value) ? ($value ? 'yes' : 'no') : $value;
            }

            yield $row;
        }
    }

    /**
     * @return list<string>
     */
    private function attributeKeys(): array
    {
        $keys = [];

        foreach (Category::query()->withTrashed()->orderBy('name')->get() as $category) {
            foreach ($category->schema()->fields as $field) {
                $keys[$field->key] = true;
            }
        }

        return array_values(array_diff(array_keys($keys), [...AssetImport::COLUMNS, ...self::EXTRA]));
    }
}
