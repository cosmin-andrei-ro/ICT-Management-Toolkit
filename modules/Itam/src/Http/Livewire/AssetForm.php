<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Location;
use Modules\Core\Domain\Models\Supplier;
use Modules\Itam\Application\Actions\ProvisionDefaultCategories;
use Modules\Itam\Application\Actions\SaveAsset;
use Modules\Itam\Domain\Models\Asset;
use Modules\Itam\Domain\Models\Category;

/**
 * New asset, or editing one's details. The attribute fields follow the category.
 */
#[Layout('core::components.layouts.app')]
final class AssetForm extends Component
{
    #[Locked]
    public ?string $assetId = null;

    public string $categoryId = '';

    public string $assetTag = '';

    public string $name = '';

    public string $manufacturer = '';

    public string $model = '';

    public string $serialNumber = '';

    /** Only for a new asset: ordered or in stock. */
    public string $status = 'in_stock';

    public string $locationId = '';

    public string $supplierId = '';

    public string $contractId = '';

    public string $purchaseDate = '';

    public string $purchaseCost = '';

    public string $currency = '';

    public string $orderNumber = '';

    public string $invoiceNumber = '';

    public string $warrantyEndsOn = '';

    public string $replaceOn = '';

    public string $notes = '';

    /** @var array<string, mixed> Attribute values of the category, keyed by attribute key. */
    public array $properties = [];

    public function mount(ProvisionDefaultCategories $provision, ?Asset $asset = null): void
    {
        if ($asset === null || ! $asset->exists) {
            $this->authorize('create', Asset::class);
            $provision->handle();
            $category = request()->query('category');
            $this->categoryId = is_string($category) && Category::query()->where('is_active', true)->whereKey($category)->exists() ? $category : '';
            $this->currency = config()->string('core.contracts.default_currency');

            return;
        }

        $this->authorize('update', $asset);
        $this->assetId = $asset->id;
        $this->categoryId = $asset->category_id;
        $this->assetTag = $asset->asset_tag;
        $this->name = $asset->name ?? '';
        $this->manufacturer = $asset->manufacturer ?? '';
        $this->model = $asset->model ?? '';
        $this->serialNumber = $asset->serial_number ?? '';
        $this->locationId = $asset->location_id ?? '';
        $this->supplierId = $asset->supplier_id ?? '';
        $this->contractId = $asset->contract_id ?? '';
        $this->purchaseDate = $asset->purchase_date?->toDateString() ?? '';
        $this->purchaseCost = $asset->purchase_cost ?? '';
        $this->currency = $asset->currency ?? config()->string('core.contracts.default_currency');
        $this->orderNumber = $asset->order_number ?? '';
        $this->invoiceNumber = $asset->invoice_number ?? '';
        $this->warrantyEndsOn = $asset->warranty_ends_on?->toDateString() ?? '';
        $this->replaceOn = $asset->replace_on?->toDateString() ?? '';
        $this->notes = $asset->notes ?? '';
        $this->properties = $asset->properties;
    }

    public function updatedCategoryId(): void
    {
        // Values of another category's attributes would be dropped anyway.
        $this->properties = [];
        $this->resetErrorBag();
    }

    public function save(SaveAsset $saveAsset): void
    {
        $asset = $this->assetId !== null ? Asset::query()->findOrFail($this->assetId) : null;
        $this->authorize($asset !== null ? 'update' : 'create', $asset ?? Asset::class);

        $this->validate([
            'categoryId' => ['required', 'uuid'],
            'assetTag' => ['nullable', 'string', 'max:64', $this->unique('asset_tag')],
            'name' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serialNumber' => ['nullable', 'string', 'max:255', $this->unique('serial_number')],
            'status' => ['required', Rule::in(['ordered', 'in_stock'])],
            'locationId' => ['nullable', 'uuid'],
            'supplierId' => ['nullable', 'uuid'],
            'contractId' => ['nullable', 'uuid'],
            'purchaseDate' => ['nullable', 'date_format:Y-m-d'],
            'purchaseCost' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'currency' => [Rule::requiredIf($this->purchaseCost !== ''), 'nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'orderNumber' => ['nullable', 'string', 'max:100'],
            'invoiceNumber' => ['nullable', 'string', 'max:100'],
            'warrantyEndsOn' => ['nullable', 'date_format:Y-m-d'],
            'replaceOn' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Scoped lookup: a category of another organization is not found.
        $category = Category::query()->findOrFail($this->categoryId);

        try {
            $asset = $saveAsset->handle($asset, $category, [
                'asset_tag' => $this->assetTag,
                'name' => $this->name,
                'manufacturer' => $this->manufacturer,
                'model' => $this->model,
                'serial_number' => $this->serialNumber,
                'status' => $this->status,
                'location_id' => $this->locationId,
                'supplier_id' => $this->supplierId,
                'contract_id' => $this->contractId,
                'purchase_date' => $this->purchaseDate,
                'purchase_cost' => $this->purchaseCost,
                'currency' => $this->currency,
                'order_number' => $this->orderNumber,
                'invoice_number' => $this->invoiceNumber,
                'warranty_ends_on' => $this->warrantyEndsOn,
                'replace_on' => $this->replaceOn,
                'notes' => $this->notes,
            ], $this->properties);
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $key => $messages) {
                $errors['properties.'.$key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        } catch (InvalidArgumentException) {
            $this->addError('locationId', __('itam::assets.ui.invalid_reference'));

            return;
        }

        $this->redirectRoute('itam.assets.show', $asset);
    }

    /**
     * Case-insensitive, like the database's unique indexes: SN-1 and sn-1 are the same.
     *
     * @param  'asset_tag'|'serial_number'  $column
     */
    private function unique(string $column): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column): void {
            $taken = is_string($value) && trim($value) !== '' && Asset::query()
                ->whereRaw($column === 'asset_tag' ? 'lower(asset_tag) = ?' : 'lower(serial_number) = ?', [mb_strtolower(trim($value))])
                ->when($this->assetId !== null, fn ($query) => $query->whereKeyNot($this->assetId))
                ->exists();

            if ($taken) {
                $fail(__('itam::assets.ui.taken'));
            }
        };
    }

    public function render(): View
    {
        $category = $this->categoryId !== '' ? Category::query()->find($this->categoryId) : null;
        $canSeeContracts = auth()->user()?->can('viewAny', Contract::class) === true;

        return view('itam::livewire.form', [
            'categories' => Category::query()->where(fn ($query) => $query->where('is_active', true)->orWhereKey($this->categoryId ?: null))->orderBy('name')->get(),
            'fields' => $category?->schema()->fields ?? [],
            'locations' => Location::paths(),
            'suppliers' => Supplier::query()->where(fn ($query) => $query->where('is_active', true)->orWhereKey($this->supplierId ?: null))->orderBy('name')->get(['id', 'name']),
            'contracts' => $canSeeContracts ? Contract::query()->with('supplier')->orderBy('title')->get(['id', 'title', 'supplier_id']) : collect(),
            'canSeeContracts' => $canSeeContracts,
        ])->title(__($this->assetId !== null ? 'itam::assets.ui.edit' : 'itam::assets.ui.new'));
    }
}
