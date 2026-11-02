<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Suppliers;

use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Domain\Models\Supplier;

#[Layout('core::components.layouts.app')]
final class SupplierForm extends Component
{
    #[Locked]
    public ?string $supplierId = null;

    public string $name = '';

    public string $legalName = '';

    public string $taxId = '';

    public string $customerNumber = '';

    public string $website = '';

    public string $supportEmail = '';

    public string $supportPhone = '';

    public string $supportHours = '';

    public string $notes = '';

    public bool $isActive = true;

    public function mount(?Supplier $supplier = null): void
    {
        if ($supplier === null || ! $supplier->exists) {
            $this->authorize('create', Supplier::class);

            return;
        }

        $this->authorize('update', $supplier);
        $this->supplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->legalName = $supplier->legal_name ?? '';
        $this->taxId = $supplier->tax_id ?? '';
        $this->customerNumber = $supplier->customer_number ?? '';
        $this->website = $supplier->website ?? '';
        $this->supportEmail = $supplier->support_email ?? '';
        $this->supportPhone = $supplier->support_phone ?? '';
        $this->supportHours = $supplier->support_hours ?? '';
        $this->notes = $supplier->notes ?? '';
        $this->isActive = $supplier->is_active;
    }

    public function save(): void
    {
        $supplier = $this->supplierId !== null ? Supplier::query()->findOrFail($this->supplierId) : null;
        $this->authorize($supplier !== null ? 'update' : 'create', $supplier ?? Supplier::class);

        $this->validate([
            'name' => ['required', 'string', 'max:255', $this->uniqueName(...)],
            'legalName' => ['nullable', 'string', 'max:255'],
            'taxId' => ['nullable', 'string', 'max:64'],
            'customerNumber' => ['nullable', 'string', 'max:64'],
            'website' => ['nullable', 'string', 'max:255', 'url:http,https'],
            'supportEmail' => ['nullable', 'string', 'max:255', 'email'],
            'supportPhone' => ['nullable', 'string', 'max:64'],
            'supportHours' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'isActive' => ['boolean'],
        ]);

        $supplier ??= new Supplier;
        $supplier->fill([
            'name' => trim($this->name),
            'legal_name' => self::blankToNull($this->legalName),
            'tax_id' => self::blankToNull($this->taxId),
            'customer_number' => self::blankToNull($this->customerNumber),
            'website' => self::blankToNull($this->website),
            'support_email' => self::blankToNull($this->supportEmail),
            'support_phone' => self::blankToNull($this->supportPhone),
            'support_hours' => self::blankToNull($this->supportHours),
            'notes' => self::blankToNull($this->notes),
            'is_active' => $this->isActive,
        ])->save();

        $this->redirectRoute('core.suppliers.show', $supplier);
    }

    /**
     * Case-insensitive, like the database's unique index.
     */
    private function uniqueName(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = is_string($value) && Supplier::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))])
            ->when($this->supplierId !== null, fn ($query) => $query->whereKeyNot($this->supplierId))
            ->exists();

        if ($taken) {
            $fail(__('core::suppliers.ui.name_taken'));
        }
    }

    private static function blankToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function render(): View
    {
        return view('core::livewire.suppliers.form')
            ->title(__($this->supplierId !== null ? 'core::suppliers.ui.edit' : 'core::suppliers.ui.new'));
    }
}
