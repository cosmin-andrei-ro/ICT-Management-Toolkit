<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Core\Http\Livewire\Contracts;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Core\Application\Contracts\SaveContract;
use Modules\Core\Domain\Enums\BillingPeriod;
use Modules\Core\Domain\Enums\ContractType;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;

#[Layout('core::components.layouts.app')]
final class ContractForm extends Component
{
    #[Locked]
    public ?string $contractId = null;

    public string $supplierId = '';

    public string $title = '';

    public string $reference = '';

    public string $type = 'support';

    public string $startDate = '';

    public string $endDate = '';

    public bool $autoRenews = false;

    public string $renewalMonths = '12';

    public string $noticeDays = '';

    public string $terminatedOn = '';

    public string $value = '';

    public string $currency = '';

    public string $billingPeriod = '';

    public string $ownerId = '';

    public string $description = '';

    public function mount(?Contract $contract = null): void
    {
        if ($contract === null || ! $contract->exists) {
            $this->authorize('create', Contract::class);
            $supplier = request()->query('supplier');
            $this->supplierId = is_string($supplier) && Supplier::query()->whereKey($supplier)->exists() ? $supplier : '';
            $this->startDate = now()->toDateString();
            $this->currency = config()->string('core.contracts.default_currency');
            $this->ownerId = (string) auth()->id();

            return;
        }

        $this->authorize('update', $contract);
        $this->contractId = $contract->id;
        $this->supplierId = $contract->supplier_id;
        $this->title = $contract->title;
        $this->reference = $contract->reference ?? '';
        $this->type = $contract->type->value;
        $this->startDate = $contract->start_date->toDateString();
        $this->endDate = $contract->end_date?->toDateString() ?? '';
        $this->autoRenews = $contract->auto_renews;
        $this->renewalMonths = (string) ($contract->renewal_months ?? 12);
        $this->noticeDays = $contract->notice_days !== null ? (string) $contract->notice_days : '';
        $this->terminatedOn = $contract->terminated_on?->toDateString() ?? '';
        $this->value = $contract->value ?? '';
        $this->currency = $contract->currency ?? config()->string('core.contracts.default_currency');
        $this->billingPeriod = $contract->billing_period->value ?? '';
        $this->ownerId = $contract->owner_id ?? '';
        $this->description = $contract->description ?? '';
    }

    public function save(SaveContract $saveContract): void
    {
        $contract = $this->contractId !== null ? Contract::query()->findOrFail($this->contractId) : null;
        $this->authorize($contract !== null ? 'update' : 'create', $contract ?? Contract::class);

        $this->validate([
            'supplierId' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::enum(ContractType::class)],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'autoRenews' => ['boolean'],
            'renewalMonths' => [Rule::requiredIf($this->autoRenews && $this->endDate !== ''), 'nullable', 'integer', 'between:1,120'],
            'noticeDays' => ['nullable', 'integer', 'between:0,730'],
            'terminatedOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'value' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'currency' => [Rule::requiredIf($this->value !== ''), 'nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'billingPeriod' => ['nullable', Rule::enum(BillingPeriod::class)],
            'ownerId' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $contract = $saveContract->handle($contract, [
                'supplier_id' => $this->supplierId,
                'title' => $this->title,
                'reference' => $this->reference,
                'type' => $this->type,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'auto_renews' => $this->autoRenews,
                'renewal_months' => $this->renewalMonths,
                'notice_days' => $this->noticeDays,
                'terminated_on' => $this->terminatedOn,
                'value' => $this->value,
                'currency' => $this->currency,
                'billing_period' => $this->billingPeriod,
                'owner_id' => $this->ownerId,
                'description' => $this->description,
            ]);
        } catch (InvalidArgumentException) {
            $this->addError('supplierId', __('core::contracts.ui.invalid_supplier_or_owner'));

            return;
        }

        $this->redirectRoute('core.contracts.show', $contract);
    }

    public function render(OrganizationContext $context): View
    {
        $organizationId = $context->id();

        return view('core::livewire.contracts.form', [
            'suppliers' => Supplier::query()->where(fn ($query) => $query->where('is_active', true)->orWhereKey($this->supplierId ?: null))->orderBy('name')->get(['id', 'name']),
            'types' => ContractType::cases(),
            'billingPeriods' => BillingPeriod::cases(),
            'people' => User::query()->where('is_active', true)->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))->orderBy('name')->get(['id', 'name']),
        ])->title(__($this->contractId !== null ? 'core::contracts.ui.edit' : 'core::contracts.ui.new'));
    }
}
