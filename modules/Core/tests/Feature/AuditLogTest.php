<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Core\Application\Audit\AuditChanges;
use Modules\Core\Application\Exports\AuditLogExport;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Audit\AuditTrail;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Livewire\Audit\AuditLogIndex;
use Modules\Core\Http\Livewire\Audit\RecordHistory;
use Modules\Core\Tests\Support\ContractScenario;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->scenario = ContractScenario::create();
    $this->auditor = $this->scenario->member(DefaultRole::Auditor);
});

describe('the audit log page', function (): void {
    it('is for the readers of the audit log; agents and requesters cannot open it', function (): void {
        $this->actingAs($this->auditor)->get(route('core.audit-log.index'))->assertOk()->assertSee('Audit log');
        $this->actingAs($this->scenario->member(DefaultRole::ComplianceManager))->get(route('core.audit-log.index'))->assertOk();

        $this->actingAs($this->scenario->agent)->get(route('core.audit-log.index'))->assertForbidden();
        $this->actingAs($this->scenario->requester)->get(route('core.audit-log.index'))->assertForbidden();
    });

    it('shows security events by default, and changes to records on request', function (): void {
        $trail = app(AuditTrail::class);
        $trail->record(AuditEvent::LoginFailed, $this->scenario->agent, null, ['reason' => 'password'], $this->scenario->agent->id);
        $trail->record(AuditEvent::SecretRevealed, $this->scenario->supplier, null, ['field' => 'licence key'], $this->scenario->manager->id);
        $this->scenario->supplier->update(['name' => 'Sample Renamed']);

        $this->actingAs($this->auditor);
        Livewire::test(AuditLogIndex::class)
            ->assertSee('Sign-in failed')->assertSee('Secret revealed')->assertDontSee('Sample Renamed')
            ->set('category', 'changes')
            ->assertSee('Sample Renamed')->assertSee('Sample Support')->assertDontSee('Sign-in failed')
            ->set('category', 'all')->set('event', AuditEvent::SecretRevealed->value)
            ->assertSee('Secret revealed')->assertDontSee('Sample Renamed');
    });

    it('filters by person, record type and dates', function (): void {
        $trail = app(AuditTrail::class);
        $trail->record(AuditEvent::Login, $this->scenario->agent, null, null, $this->scenario->agent->id);
        $trail->record(AuditEvent::Login, $this->scenario->manager, null, null, $this->scenario->manager->id);

        $this->actingAs($this->auditor);
        $page = Livewire::test(AuditLogIndex::class)->set('person', $this->scenario->manager->email);
        expect($page->viewData('entries')->pluck('user_id')->unique()->all())->toBe([$this->scenario->manager->id]);

        $page->set('person', '')->set('type', 'core.supplier')->set('category', 'all');
        expect($page->viewData('entries')->pluck('auditable_type')->unique()->all())->toBe(['core.supplier']);

        // The filter's dates are in the display time zone, whose day can differ from UTC's.
        $today = now(config()->string('app.display_timezone'));
        $page->set('type', '')->set('from', $today->copy()->addDay()->toDateString());
        expect($page->viewData('entries'))->toHaveCount(0);

        $page->set('from', $today->copy()->subDay()->toDateString())->set('until', $today->toDateString());
        expect($page->viewData('entries')->total())->toBeGreaterThan(0);
    });

    it('keeps to the organization: its records, and sign-ins of its members only', function (): void {
        $other = Organization::factory()->create();
        [$stranger] = app(OrganizationContext::class)->runAs($other, fn (): array => [
            User::factory()->memberOf($other)->create(['name' => 'Stranger Person']),
            Supplier::factory()->create(['name' => 'Their Vendor']),
        ]);
        // Sign-ins are recorded without an organization.
        $signIn = fn (AuditEvent $event, ?User $user, array $values) => (new AuditLog)->forceFill([
            'organization_id' => null, 'user_id' => $user?->id, 'event' => $event, 'auditable_type' => $user?->getMorphClass(), 'auditable_id' => $user?->id, 'new_values' => $values,
        ])->save();
        $signIn(AuditEvent::Login, $stranger, []);
        $signIn(AuditEvent::Login, $this->scenario->agent, []);
        $signIn(AuditEvent::LoginFailed, null, ['login' => 'nobody@unknown.example']);

        $this->actingAs($this->auditor);
        Livewire::test(AuditLogIndex::class)->set('category', 'all')
            ->assertSee($this->scenario->agent->name)
            ->assertDontSee('Stranger Person')->assertDontSee('Their Vendor')->assertDontSee('nobody@unknown.example');

        // Failed sign-ins for unknown accounts belong to no organization: administrators only.
        $this->actingAs($this->scenario->member(DefaultRole::Administrator));
        Livewire::test(AuditLogIndex::class)->assertSee('nobody@unknown.example')->assertDontSee('Stranger Person');
    });

    it('exports what the filters show, and the export is itself audited', function (): void {
        app(AuditTrail::class)->record(AuditEvent::SecretRevealed, $this->scenario->supplier, null, ['field' => 'licence key'], $this->scenario->manager->id);

        $this->actingAs($this->auditor);
        Livewire::test(AuditLogIndex::class)->set('event', AuditEvent::SecretRevealed->value)->call('export');

        $export = Export::query()->where('type', AuditLogExport::TYPE)->sole();
        expect($export->parameters['event'])->toBe('secret_revealed')
            ->and(AuditLog::query()->where('event', AuditEvent::Exported)->where('auditable_id', $export->id)->exists())->toBeTrue();

        $rows = iterator_to_array(app(AuditLogExport::class)->sheets($export)[0]->rows, false);
        expect($rows)->toHaveCount(1)
            ->and($rows[0][1])->toBe('Secret revealed')
            ->and($rows[0][2])->toContain($this->scenario->manager->email)
            ->and($rows[0][5])->toBe('Field')
            ->and($rows[0][7])->toBe('licence key');
    });
});

describe('the history of a record', function (): void {
    it('shows who changed which field, from what to what, with people by name', function (): void {
        $this->actingAs($this->scenario->manager);
        $supplier = $this->scenario->supplier;
        $supplier->update(['name' => 'Sample Renamed', 'is_active' => false]);

        Livewire::test(RecordHistory::class, ['record' => $supplier])
            ->assertDontSee('Sample Renamed')
            ->call('toggle')
            ->assertSee('Changed')->assertSee('Sample Support')->assertSee('Sample Renamed')->assertSee($this->scenario->manager->name)
            ->assertSee('Is Active');
    });

    it('is on the record page for those who may change the record and for audit log readers only', function (): void {
        $this->actingAs($this->scenario->manager)->get(route('core.suppliers.show', $this->scenario->supplier))->assertSee('Show history');
        $this->actingAs($this->auditor)->get(route('core.suppliers.show', $this->scenario->supplier))->assertSee('Show history');
        $this->actingAs($this->scenario->agent)->get(route('core.suppliers.show', $this->scenario->supplier))->assertOk()->assertDontSee('Show history');

        $this->actingAs($this->scenario->agent);
        Livewire::test(RecordHistory::class, ['record' => $this->scenario->supplier])->assertForbidden();
    });

    it('stops showing the history on a page left open once the right is gone', function (): void {
        $this->actingAs($this->scenario->manager);
        $page = Livewire::test(RecordHistory::class, ['record' => $this->scenario->supplier])->assertOk();

        $this->scenario->manager->syncRoles([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->scenario->manager->unsetRelation('roles')->unsetRelation('permissions');

        $page->call('toggle')->assertForbidden();
    });

    it('reads values as people do', function (): void {
        $entry = new AuditLog;
        $entry->forceFill([
            'old_values' => ['owner_id' => $this->scenario->manager->id, 'is_active' => true, 'tags' => ['a', 'b']],
            'new_values' => ['owner_id' => null, 'is_active' => false, 'tags' => ['a']],
        ]);

        expect(AuditChanges::for([$entry])->rows($entry))->toBe([
            ['field' => 'Owner', 'old' => $this->scenario->manager->name, 'new' => '—'],
            ['field' => 'Is Active', 'old' => 'Yes', 'new' => 'No'],
            ['field' => 'Tags', 'old' => '["a","b"]', 'new' => '["a"]'],
        ]);
    });
});

it('is in the menu of audit log readers', function (): void {
    $this->actingAs($this->auditor)->get(route('core.audit-log.index'))
        ->assertSeeInOrder(['data-nav-menu', 'Audit log'], false);
});
