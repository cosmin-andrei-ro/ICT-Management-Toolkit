<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Suppliers;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Application\Suppliers\SaveSupplierContact;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\SupplierContact;

/**
 * One supplier: how to reach their support, the people to call, and (for those
 * allowed to see contracts) the contracts with them.
 *
 * @property-read Supplier $supplier Computed.
 */
#[Layout('core::components.layouts.app')]
final class ShowSupplier extends Component
{
    #[Locked]
    public string $supplierId = '';

    /** The contact being edited; "new" while adding one; null when the form is closed. */
    #[Locked]
    public ?string $editingContactId = null;

    public string $contactName = '';

    public string $contactRole = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $contactNotes = '';

    public function mount(Supplier $supplier): void
    {
        $this->authorize('view', $supplier);
        $this->supplierId = $supplier->id;
    }

    #[Computed]
    public function supplier(): Supplier
    {
        return Supplier::query()->with('contacts')->findOrFail($this->supplierId);
    }

    public function startContact(?string $contactId = null): void
    {
        $this->authorize('update', $this->supplier);
        $this->resetErrorBag();

        $contact = $contactId !== null ? $this->contact($contactId) : null;
        $this->editingContactId = $contact->id ?? 'new';
        $this->contactName = $contact->name ?? '';
        $this->contactRole = $contact->role ?? '';
        $this->contactEmail = $contact->email ?? '';
        $this->contactPhone = $contact->phone ?? '';
        $this->contactNotes = $contact->notes ?? '';
    }

    public function cancelContact(): void
    {
        $this->reset('editingContactId', 'contactName', 'contactRole', 'contactEmail', 'contactPhone', 'contactNotes');
    }

    public function saveContact(SaveSupplierContact $saveContact): void
    {
        $this->authorize('update', $this->supplier);

        if ($this->editingContactId === null) {
            return;
        }

        $this->validate([
            'contactName' => ['required', 'string', 'max:255'],
            'contactRole' => ['nullable', 'string', 'max:255'],
            'contactEmail' => ['nullable', 'string', 'max:255', 'email'],
            'contactPhone' => ['nullable', 'string', 'max:64'],
            'contactNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $saveContact->handle($this->supplier, $this->editingContactId === 'new' ? null : $this->contact($this->editingContactId), [
            'name' => $this->contactName,
            'role' => $this->contactRole,
            'email' => $this->contactEmail,
            'phone' => $this->contactPhone,
            'notes' => $this->contactNotes,
        ]);

        $this->cancelContact();
        unset($this->supplier);
    }

    public function removeContact(string $contactId): void
    {
        $this->authorize('update', $this->supplier);
        $this->contact($contactId)->delete();
        unset($this->supplier);
    }

    public function delete(): void
    {
        $supplier = $this->supplier;
        $this->authorize('delete', $supplier);

        // Contracts keep their supplier; the database refuses too (ON DELETE RESTRICT
        // only applies to hard deletes, so it is checked here).
        if ($supplier->contracts()->exists()) {
            $this->addError('delete', __('core::suppliers.ui.has_contracts'));

            return;
        }

        $supplier->delete();
        $this->redirectRoute('core.suppliers.index');
    }

    public function render(): View
    {
        $supplier = $this->supplier;
        $canSeeContracts = auth()->user()?->can('viewAny', Contract::class) === true;

        return view('core::livewire.suppliers.show', [
            'supplier' => $supplier,
            'canSeeContracts' => $canSeeContracts,
            'contracts' => $canSeeContracts ? $supplier->contracts()->orderByDesc('start_date')->limit(100)->get() : collect(),
            'expiringDays' => Contract::expiringDays(),
        ])->title($supplier->name);
    }

    private function contact(string $contactId): SupplierContact
    {
        return SupplierContact::query()->where('supplier_id', $this->supplierId)->findOrFail($contactId);
    }
}
