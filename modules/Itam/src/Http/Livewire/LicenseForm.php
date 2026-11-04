<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Itam\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Itam\Application\Actions\SaveLicense;
use Modules\Itam\Domain\Enums\LicenseModel;
use Modules\Itam\Domain\Exceptions\LicenseSeatsInUse;
use Modules\Itam\Domain\Models\License;
use Modules\Itam\Domain\Models\SoftwareProduct;

/**
 * New licence, or editing one. The key is never sent to the browser: the form
 * only takes a new one, or removes the current one.
 */
#[Layout('core::components.layouts.app')]
final class LicenseForm extends Component
{
    #[Locked]
    public ?string $licenseId = null;

    public string $productId = '';

    public string $newProduct = '';

    public string $name = '';

    public string $model = 'per_user';

    public string $quantity = '1';

    public string $newKey = '';

    public bool $removeKey = false;

    public string $supplierId = '';

    public string $contractId = '';

    public string $purchaseDate = '';

    public string $cost = '';

    public string $currency = '';

    public string $startsOn = '';

    public string $expiresOn = '';

    public string $notes = '';

    public function mount(?License $license = null): void
    {
        if ($license === null || ! $license->exists) {
            $this->authorize('create', License::class);
            $product = request()->query('product');
            $this->productId = is_string($product) && SoftwareProduct::query()->whereKey($product)->exists() ? $product : '';
            $this->currency = config()->string('core.contracts.default_currency');
            $newProduct = request()->query('new_product');
            $this->newProduct = $this->productId === '' && is_string($newProduct) ? mb_substr($newProduct, 0, 255) : '';

            return;
        }

        $this->authorize('update', $license);
        $this->licenseId = $license->id;
        $this->productId = $license->product_id;
        $this->name = $license->name;
        $this->model = $license->model->value;
        $this->quantity = (string) ($license->quantity ?? 1);
        $this->supplierId = $license->supplier_id ?? '';
        $this->contractId = $license->contract_id ?? '';
        $this->purchaseDate = $license->purchase_date?->toDateString() ?? '';
        $this->cost = $license->cost ?? '';
        $this->currency = $license->currency ?? config()->string('core.contracts.default_currency');
        $this->startsOn = $license->starts_on?->toDateString() ?? '';
        $this->expiresOn = $license->expires_on?->toDateString() ?? '';
        $this->notes = $license->notes ?? '';
    }

    public function save(SaveLicense $saveLicense): void
    {
        $license = $this->licenseId !== null ? License::query()->findOrFail($this->licenseId) : null;
        $this->authorize($license !== null ? 'update' : 'create', $license ?? License::class);

        $this->validate([
            'productId' => [Rule::requiredIf(trim($this->newProduct) === ''), 'nullable', 'uuid'],
            'newProduct' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'model' => ['required', Rule::enum(LicenseModel::class)],
            'quantity' => [Rule::requiredIf($this->model !== LicenseModel::Site->value), 'nullable', 'integer', 'between:1,1000000'],
            'newKey' => ['nullable', 'string', 'max:5000'],
            'removeKey' => ['boolean'],
            'supplierId' => ['nullable', 'uuid'],
            'contractId' => ['nullable', 'uuid'],
            'purchaseDate' => ['nullable', 'date_format:Y-m-d'],
            'cost' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'currency' => [Rule::requiredIf($this->cost !== ''), 'nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'startsOn' => ['nullable', 'date_format:Y-m-d'],
            'expiresOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startsOn'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $license = $saveLicense->handle($license, $this->productId, $this->newProduct, [
                'name' => $this->name,
                'model' => $this->model,
                'quantity' => $this->quantity,
                'supplier_id' => $this->supplierId,
                'contract_id' => $this->contractId,
                'purchase_date' => $this->purchaseDate,
                'cost' => $this->cost,
                'currency' => $this->currency,
                'starts_on' => $this->startsOn,
                'expires_on' => $this->expiresOn,
                'notes' => $this->notes,
            ], $this->newKey, $this->removeKey);
        } catch (LicenseSeatsInUse) {
            $this->addError('model', __('itam::licenses.ui.model_has_seats'));

            return;
        } catch (InvalidArgumentException) {
            $this->addError('productId', __('itam::licenses.ui.invalid_reference'));

            return;
        } finally {
            // The key typed in never stays in the page state.
            $this->reset('newKey');
        }

        $this->redirectRoute('itam.licenses.show', $license);
    }

    public function render(): View
    {
        $license = $this->licenseId !== null ? License::query()->find($this->licenseId) : null;
        $canSeeContracts = auth()->user()?->can('viewAny', Contract::class) === true;

        return view('itam::livewire.licenses.form', [
            'products' => SoftwareProduct::query()->orderBy('name')->get(['id', 'name']),
            'models' => LicenseModel::cases(),
            'hasKey' => $license?->hasKey() === true,
            'suppliers' => Supplier::query()->where(fn ($query) => $query->where('is_active', true)->orWhereKey($this->supplierId ?: null))->orderBy('name')->get(['id', 'name']),
            'contracts' => $canSeeContracts ? Contract::query()->with('supplier')->orderBy('title')->get(['id', 'title', 'supplier_id']) : collect(),
            'canSeeContracts' => $canSeeContracts,
        ])->title(__($this->licenseId !== null ? 'itam::licenses.ui.edit' : 'itam::licenses.ui.new'));
    }
}
