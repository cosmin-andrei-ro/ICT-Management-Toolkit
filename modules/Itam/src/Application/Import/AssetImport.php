<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Application\Import;

use DateTimeInterface;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Core\Application\Imports\ImportHasErrors;
use Modules\Core\Application\Imports\ImportRow;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Itam\Application\Actions\AssignAsset;
use Modules\Itam\Application\Actions\SaveAsset;
use Modules\Itam\Domain\Enums\AssetStatus;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;

/**
 * Assets from a spreadsheet, for the first inventory and for deliveries: one row
 * per asset, matched by asset tag, or by serial number when the row has no tag
 * (update), or new (create). A "holder" column hands the asset to that person, as on the asset
 * page. An empty cell keeps the current value of an existing asset. The status
 * of an existing asset changes on its page, not by import. analyse() saves
 * nothing; apply() re-checks and saves every row, or none.
 */
final readonly class AssetImport
{
    public const array COLUMNS = ['category', 'asset_tag', 'serial_number', 'name', 'manufacturer', 'model', 'status', 'holder', 'location', 'supplier', 'purchase_date', 'purchase_cost', 'currency', 'order_number', 'invoice_number', 'warranty_ends_on', 'replace_on', 'notes'];

    private const array DATES = ['purchase_date', 'warranty_ends_on', 'replace_on'];

    private const array TEXTS = ['name' => 255, 'manufacturer' => 255, 'model' => 255, 'order_number' => 100, 'invoice_number' => 100, 'notes' => 5000];

    public function __construct(
        private ConnectionInterface $db,
        private OrganizationContext $context,
        private ValidationFactory $validation,
        private SaveAsset $saveAsset,
        private AssignAsset $assignAsset,
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
    public function apply(array $rows, User $actor): int
    {
        $plan = $this->plan($rows);

        if (array_filter($plan, fn (array $planned): bool => $planned['row']->action === ImportRow::ERROR) !== []) {
            throw new ImportHasErrors;
        }

        return $this->db->transaction(function () use ($plan, $actor): int {
            foreach ($plan as $planned) {
                if ($planned['category'] === null) {
                    continue;
                }

                $asset = $this->saveAsset->handle($planned['asset'], $planned['category'], $planned['fields'], $planned['properties']);

                if ($planned['holder'] !== null && $asset->assigned_to_id !== $planned['holder']->id) {
                    $this->assignAsset->handle($asset, $planned['holder'], $actor, notes: __('itam::import.assigned_by_import'), notify: false);
                }
            }

            return count($plan);
        });
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return list<array{row: ImportRow, asset: ?Asset, category: ?Category, holder: ?User, fields: array<string, ?string>, properties: array<string, mixed>}>
     */
    private function plan(array $rows): array
    {
        $organizationId = $this->context->requireId();
        $categories = Category::query()->where('is_active', true)->get();
        $assets = Asset::query()->with('category')->get();
        $byTag = $assets->keyBy(fn (Asset $asset): string => mb_strtolower($asset->asset_tag));
        $bySerial = $assets->filter(fn (Asset $asset): bool => $asset->serial_number !== null)->keyBy(fn (Asset $asset): string => mb_strtolower((string) $asset->serial_number));
        $people = User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))->get()->keyBy(fn (User $user): string => mb_strtolower($user->email));
        $locations = $this->locations();
        $suppliers = Supplier::query()->get()->keyBy(fn (Supplier $supplier): string => mb_strtolower($supplier->name));
        $seenTags = [];
        $seenSerials = [];
        $result = [];

        foreach ($rows as $row) {
            $values = array_map(trim(...), $row['values']);
            $errors = [];
            $tag = $values['asset_tag'] ?? '';
            $serial = $values['serial_number'] ?? '';
            // By serial number only without a tag: a new tag with a known serial number is a mistake, not a relabel.
            $asset = $tag !== '' ? $byTag->get(mb_strtolower($tag)) : ($serial !== '' ? $bySerial->get(mb_strtolower($serial)) : null);

            if ($tag !== '' && isset($seenTags[mb_strtolower($tag)])) {
                $errors[] = ImportRow::message('itam::import.errors.duplicate', ['column' => 'asset_tag', 'line' => $seenTags[mb_strtolower($tag)]]);
            }

            if ($serial !== '' && isset($seenSerials[mb_strtolower($serial)])) {
                $errors[] = ImportRow::message('itam::import.errors.duplicate', ['column' => 'serial_number', 'line' => $seenSerials[mb_strtolower($serial)]]);
            }

            if ($tag !== '') {
                $seenTags[mb_strtolower($tag)] ??= $row['line'];
            }

            if ($serial !== '') {
                $seenSerials[mb_strtolower($serial)] ??= $row['line'];
            }

            // A serial number already on another asset would break the unique index.
            if ($serial !== '' && ($other = $bySerial->get(mb_strtolower($serial))) !== null && $other->id !== $asset?->id) {
                $errors[] = ImportRow::message('itam::import.errors.serial_taken', ['tag' => $other->asset_tag]);
            }

            $category = $this->category($categories, $values['category'] ?? '') ?? $asset?->category;

            if ($category === null) {
                $errors[] = ImportRow::message('itam::import.errors.category');
            } elseif ($asset !== null && $asset->category_id !== $category->id) {
                $errors[] = ImportRow::message('itam::import.errors.category_change');
            }

            $status = mb_strtolower($values['status'] ?? '');
            $statusValue = $status === '' ? null : collect([AssetStatus::Ordered, AssetStatus::InStock])
                ->first(fn (AssetStatus $case): bool => $case->value === $status || mb_strtolower($case->label()) === $status)?->value;

            if ($asset !== null && $status !== '' && $status !== $asset->status->value && mb_strtolower($asset->status->label()) !== $status) {
                $errors[] = ImportRow::message('itam::import.errors.status_change');
            } elseif ($asset === null && $status !== '' && $statusValue === null) {
                $errors[] = ImportRow::message('itam::import.errors.status', ['value' => $status]);
            }

            $holder = $this->lookup($people, $values['holder'] ?? '', 'holder', $errors);

            if ($holder !== null && $asset !== null && $asset->assigned_to_id !== $holder->id
                && $asset->status !== AssetStatus::Deployed && ! $asset->status->canBecome(AssetStatus::Deployed)) {
                $errors[] = ImportRow::message('itam::import.errors.cannot_hand_out', ['status' => $asset->status->label()]);
            }

            $fields = [
                'asset_tag' => $tag !== '' ? $tag : $asset?->asset_tag,
                'serial_number' => $serial !== '' ? $serial : $asset?->serial_number,
                'status' => $statusValue,
                'location_id' => $this->lookup($locations, $values['location'] ?? '', 'location', $errors)->id ?? $asset?->location_id,
                'supplier_id' => $this->lookup($suppliers, $values['supplier'] ?? '', 'supplier', $errors)->id ?? $asset?->supplier_id,
                'contract_id' => $asset?->contract_id,
                'currency' => ($values['currency'] ?? '') !== '' ? $values['currency'] : $asset?->currency,
            ];

            foreach (self::TEXTS as $column => $max) {
                $value = $values[$column] ?? '';

                if (mb_strlen($value) > $max) {
                    $errors[] = ImportRow::message('itam::import.errors.too_long', ['column' => $column, 'max' => $max]);
                }

                $current = $asset?->getAttribute($column);
                $fields[$column] = $value !== '' ? $value : (is_string($current) ? $current : null);
            }

            foreach (self::DATES as $column) {
                $value = $values[$column] ?? '';

                if ($value !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $errors[] = ImportRow::message('itam::import.errors.date', ['column' => $column, 'value' => $value]);
                }

                $current = $asset?->getAttribute($column);
                $fields[$column] = $value !== '' ? $value : ($current instanceof DateTimeInterface ? $current->format('Y-m-d') : null);
            }

            $cost = str_replace(',', '.', $values['purchase_cost'] ?? '');

            if ($cost !== '' && ! preg_match('/^\d{1,12}(\.\d{1,2})?$/', $cost)) {
                $errors[] = ImportRow::message('itam::import.errors.cost', ['value' => $cost]);
            }

            $fields['purchase_cost'] = $cost !== '' ? $cost : $asset?->purchase_cost;

            if ($fields['purchase_cost'] !== null && ! preg_match('/^[A-Za-z]{3}$/', (string) $fields['currency'])) {
                $errors[] = ImportRow::message('itam::import.errors.currency');
            }

            $properties = $asset !== null ? $asset->properties : [];

            if ($category !== null) {
                foreach ($category->schema()->fields as $field) {
                    if (($values[$field->key] ?? '') !== '') {
                        $properties[$field->key] = $values[$field->key];
                    }
                }

                try {
                    $category->schema()->answer($properties, $this->validation);
                } catch (ValidationException $exception) {
                    foreach ($exception->validator->errors()->all() as $message) {
                        $errors[] = $message;
                    }
                }
            }

            $label = trim(($tag !== '' ? $tag : ($asset->asset_tag ?? '')).' '.trim(($values['manufacturer'] ?? '').' '.($values['model'] ?? '')));

            $result[] = [
                'row' => new ImportRow($row['line'], $label !== '' ? $label : ($serial !== '' ? $serial : '—'), match (true) {
                    $errors !== [] => ImportRow::ERROR,
                    $asset === null => ImportRow::CREATE,
                    default => ImportRow::UPDATE,
                }, $errors),
                'asset' => $asset,
                'category' => $category,
                'holder' => $holder,
                'fields' => $fields,
                'properties' => $properties,
            ];
        }

        return $result;
    }

    /**
     * Locations by full name ("Site › Room") or, when unique, by their own name.
     *
     * @return Collection<string, Location>
     */
    private function locations(): Collection
    {
        $all = Location::query()->where('is_active', true)->get()->keyBy('id');
        $known = [];
        $names = [];

        foreach (Location::paths() as $id => $path) {
            $location = $all->get($id);

            if ($location === null) {
                continue;
            }

            $known[mb_strtolower(str_replace(' › ', ' > ', $path))] = $location;
            $known[mb_strtolower($path)] = $location;
            $names[mb_strtolower($location->name)][] = $location;
        }

        foreach ($names as $name => $matches) {
            if (count($matches) === 1) {
                $known[$name] ??= $matches[0];
            }
        }

        return new Collection($known);
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function category(Collection $categories, string $value): ?Category
    {
        $value = mb_strtolower(trim($value));

        return $value === '' ? null : $categories->first(fn (Category $category): bool => $category->key === $value || mb_strtolower($category->name) === $value);
    }

    /**
     * @template TModel of User|Location|Supplier
     *
     * @param  Collection<string, TModel>  $known
     * @param  list<string>  $errors
     * @return TModel|null
     */
    private function lookup(Collection $known, string $value, string $column, array &$errors): User|Location|Supplier|null
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        $match = $known->get($value);

        if ($match === null) {
            $errors[] = ImportRow::message('itam::import.errors.'.$column, ['value' => $value]);
        }

        return $match;
    }
}
