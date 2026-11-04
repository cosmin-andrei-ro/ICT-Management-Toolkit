<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Enums\ContractStatus;
use Modules\Core\Domain\Models\Contract;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\SupplierContact;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Livewire\Contracts\ContractForm;
use Modules\Core\Http\Livewire\Contracts\ContractIndex;
use Modules\Core\Http\Livewire\Contracts\ShowContract;
use Modules\Core\Http\Livewire\Suppliers\ShowSupplier;
use Modules\Core\Http\Livewire\Suppliers\SupplierForm;
use Modules\Core\Http\Livewire\Suppliers\SupplierIndex;
use Modules\Core\Tests\Support\ContractScenario;

beforeEach(function (): void {
    $this->scenario = ContractScenario::create();
    $this->actingAs($this->scenario->manager);
});

describe('suppliers', function (): void {
    it('are created and edited by asset managers, with unique names regardless of case', function (): void {
        Livewire::test(SupplierForm::class)
            ->set('name', 'sample support')->call('save')->assertHasErrors(['name'])
            ->set('name', 'Fabrica')->set('supportEmail', 'not-an-email')->set('website', 'javascript:alert(1)')->call('save')
            ->assertHasErrors(['supportEmail', 'website'])
            ->set('supportEmail', 'help@fabrica.example')->set('website', 'https://fabrica.example')->set('supportPhone', '+40 21 000 0000')
            ->call('save')->assertHasNoErrors();

        $supplier = Supplier::query()->where('name', 'Fabrica')->sole();
        expect($supplier->support_email)->toBe('help@fabrica.example')
            ->and(AuditLog::query()->where('event', AuditEvent::Created)->where('auditable_id', $supplier->id)->exists())->toBeTrue();

        Livewire::test(SupplierForm::class, ['supplier' => $supplier])->set('isActive', false)->call('save')->assertHasNoErrors();
        expect($supplier->fresh()->is_active)->toBeFalse();
    });

    it('are seen by agents, who cannot change them; requesters do not see them', function (): void {
        $this->actingAs($this->scenario->agent);
        $this->get(route('core.suppliers.index'))->assertOk()->assertSee('Sample Support');
        $this->get(route('core.suppliers.show', $this->scenario->supplier))->assertOk();
        $this->get(route('core.suppliers.create'))->assertForbidden();
        $this->get(route('core.suppliers.edit', $this->scenario->supplier))->assertForbidden();
        Livewire::test(ShowSupplier::class, ['supplier' => $this->scenario->supplier])->call('startContact')->assertForbidden();

        $this->actingAs($this->scenario->requester)->get(route('core.suppliers.index'))->assertForbidden();
    });

    it('stay inside their organization', function (): void {
        $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Supplier::factory()->create(['name' => 'Their Vendor']));

        Livewire::test(SupplierIndex::class)->assertSee('Sample Support')->assertDontSee('Their Vendor');
        $this->get(route('core.suppliers.show', $theirs))->assertNotFound();
    });

    it('keep contacts, added, edited and removed on the supplier page', function (): void {
        $page = Livewire::test(ShowSupplier::class, ['supplier' => $this->scenario->supplier])
            ->call('startContact')
            ->set('contactName', 'Ana Pop')->set('contactRole', 'Account manager')->set('contactEmail', 'ana@sample.example')
            ->call('saveContact')->assertHasNoErrors()
            ->assertSee('Ana Pop')->assertSee('Account manager');

        $contact = SupplierContact::query()->sole();
        expect($contact->supplier_id)->toBe($this->scenario->supplier->id);

        $page->call('startContact', $contact->id)->set('contactPhone', '+40 700 000 000')->call('saveContact')->assertHasNoErrors();
        expect($contact->fresh()->phone)->toBe('+40 700 000 000');

        $page->call('removeContact', $contact->id);
        expect(SupplierContact::query()->exists())->toBeFalse();
    });

    it('cannot edit the contact of another supplier through this page', function (): void {
        $other = Supplier::factory()->create();
        $contact = SupplierContact::factory()->create(['supplier_id' => $other->id]);

        Livewire::test(ShowSupplier::class, ['supplier' => $this->scenario->supplier])->call('removeContact', $contact->id)->assertNotFound();
        expect($contact->fresh())->not->toBeNull();
    });

    it('are not deleted while they have contracts', function (): void {
        Contract::factory()->create(['supplier_id' => $this->scenario->supplier->id]);

        Livewire::test(ShowSupplier::class, ['supplier' => $this->scenario->supplier])->call('delete')->assertHasErrors(['delete']);
        expect($this->scenario->supplier->fresh()->trashed())->toBeFalse();
    });
});

describe('contracts', function (): void {
    it('are created with their terms, normalized and audited', function (): void {
        Livewire::test(ContractForm::class)
            ->assertSet('currency', 'EUR')
            ->set('supplierId', $this->scenario->supplier->id)
            ->set('title', 'Server maintenance')
            ->set('startDate', '2026-01-01')->set('endDate', '2025-12-31')
            ->set('value', '12.345')
            ->call('save')
            ->assertHasErrors(['endDate', 'value'])
            ->set('endDate', '2026-12-31')->set('noticeDays', '60')->set('autoRenews', true)->set('renewalMonths', '12')
            ->set('value', '1200.50')->set('currency', 'ron')->set('billingPeriod', 'yearly')
            ->call('save')
            ->assertHasNoErrors();

        $contract = Contract::query()->sole();
        expect($contract->currency)->toBe('RON')
            ->and($contract->value)->toBe('1200.50')
            ->and($contract->deadline()?->toDateString())->toBe('2026-11-01')
            ->and($contract->owner_id)->toBe($this->scenario->manager->id)
            ->and(AuditLog::query()->where('event', AuditEvent::Created)->where('auditable_id', $contract->id)->exists())->toBeTrue();
    });

    it('drop renewal and notice settings that need an end date', function (): void {
        Livewire::test(ContractForm::class)
            ->set('supplierId', $this->scenario->supplier->id)->set('title', 'Open-ended service')
            ->set('autoRenews', true)->set('noticeDays', '30')
            ->call('save')->assertHasNoErrors();

        $contract = Contract::query()->sole();
        expect($contract->auto_renews)->toBeFalse()->and($contract->renewal_months)->toBeNull()->and($contract->notice_days)->toBeNull();
    });

    it('refuse a supplier or an owner of another organization', function (): void {
        $other = Organization::factory()->create();
        $theirs = app(OrganizationContext::class)->runAs($other, fn () => Supplier::factory()->create());

        Livewire::test(ContractForm::class)
            ->set('supplierId', $theirs->id)->set('title', 'Sneaky')
            ->call('save')->assertHasErrors(['supplierId']);

        $stranger = User::factory()->memberOf($other)->create();
        Livewire::test(ContractForm::class)
            ->set('supplierId', $this->scenario->supplier->id)->set('title', 'Sneaky')->set('ownerId', $stranger->id)
            ->call('save')->assertHasErrors(['supplierId']);

        expect(Contract::query()->exists())->toBeFalse();
    });

    it('are hidden from agents and requesters, and from other organizations', function (): void {
        $contract = Contract::factory()->create(['supplier_id' => $this->scenario->supplier->id, 'title' => 'Secret terms']);
        $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Contract::factory()->create(['title' => 'Their terms']));

        Livewire::test(ContractIndex::class)->assertSee('Secret terms')->assertDontSee('Their terms');
        $this->get(route('core.contracts.show', $theirs))->assertNotFound();

        $this->actingAs($this->scenario->agent);
        $this->get(route('core.contracts.index'))->assertForbidden();
        $this->get(route('core.contracts.show', $contract))->assertForbidden();
        $this->get(route('core.suppliers.show', $this->scenario->supplier))->assertOk()->assertDontSee('Secret terms');

        $this->actingAs($this->scenario->requester)->get(route('core.contracts.show', $contract))->assertForbidden();
    });

    it('filter by status computed from the dates', function (): void {
        $make = fn (string $title, array $attributes) => Contract::factory()->create(['supplier_id' => $this->scenario->supplier->id, 'title' => $title, ...$attributes]);
        $today = CarbonImmutable::today();
        $make('Running', ['start_date' => $today->subYear(), 'end_date' => $today->addYear()]);
        $make('Open ended', ['start_date' => $today->subYear(), 'end_date' => null]);
        $make('Near deadline', ['start_date' => $today->subYear(), 'end_date' => $today->addDays(100), 'notice_days' => 30]);
        $make('Over', ['start_date' => $today->subYears(2), 'end_date' => $today->subDay()]);
        $make('Future', ['start_date' => $today->addMonth(), 'end_date' => $today->addYears(2)]);
        $make('Cancelled', ['start_date' => $today->subYear(), 'end_date' => $today->addYear(), 'terminated_on' => $today->subDay()]);

        $expected = [
            'active' => ['Running', 'Open ended'],
            'expiring' => ['Near deadline'],
            'expired' => ['Over'],
            'upcoming' => ['Future'],
            'terminated' => ['Cancelled'],
        ];

        foreach ($expected as $status => $titles) {
            $sql = Contract::query()->withStatus(ContractStatus::from($status), $today, 90)->pluck('title')->sort()->values()->all();
            $php = Contract::query()->get()->filter(fn (Contract $contract): bool => $contract->statusOn($today, 90)->value === $status)->pluck('title')->sort()->values()->all();

            expect($sql)->toBe(collect($titles)->sort()->values()->all())->and($php)->toBe($sql);
        }

        Livewire::test(ContractIndex::class)->set('status', 'expiring')->assertSee('Near deadline')->assertDontSee('Running');
    });

    it('keep their documents, downloadable only by those who see the contract', function (): void {
        Storage::fake('attachments');
        $contract = Contract::factory()->create(['supplier_id' => $this->scenario->supplier->id]);

        Livewire::test(ShowContract::class, ['contract' => $contract])
            ->set('files', [UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.4\n%test\n")])
            ->call('uploadDocuments')->assertHasNoErrors()
            ->assertSee('signed.pdf');

        $document = $contract->attachments()->sole();
        $this->get(route('core.attachments.download', $document))->assertOk();
        $this->actingAs($this->scenario->agent)->get(route('core.attachments.download', $document))->assertForbidden();

        $this->actingAs($this->scenario->manager);
        Livewire::test(ShowContract::class, ['contract' => $contract])->call('removeDocument', $document->id);
        expect($contract->attachments()->exists())->toBeFalse();
    });

    it('cannot be changed by agents', function (): void {
        $contract = Contract::factory()->create(['supplier_id' => $this->scenario->supplier->id]);
        $this->actingAs($this->scenario->agent);

        $this->get(route('core.contracts.create'))->assertForbidden();
        $this->get(route('core.contracts.edit', $contract))->assertForbidden();
    });
});
