<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Cmdb\Http\Filament\Resources\CiTypes\Pages\ManageCiTypes;
use Modules\Cmdb\Http\Livewire\ItemForm;
use Modules\Cmdb\Http\Livewire\ItemIndex;
use Modules\Cmdb\Http\Livewire\ShowItem;
use Modules\Cmdb\Tests\Support\CmdbScenario;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    $this->cmdb = CmdbScenario::create();
    $this->actingAs($this->cmdb->agent);
});

it('lists items with filters and keeps requesters out', function (): void {
    $db = $this->cmdb->item('payroll-db', ['criticality' => 'critical', 'environment' => 'production']);
    $test = $this->cmdb->item('payroll-test', ['environment' => 'test']);
    $foreign = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => ConfigurationItem::factory()->create(['name' => 'their-db']));

    Livewire::test(ItemIndex::class)
        ->assertSee('payroll-db')->assertSee('payroll-test')->assertDontSee('their-db')
        ->set('environment', 'production')
        ->assertSee('payroll-db')->assertDontSee('payroll-test');

    $this->actingAs($this->cmdb->requester)->get(route('cmdb.items.index'))->assertForbidden();
    $this->actingAs($this->cmdb->agent)->get(route('cmdb.items.show', $foreign))->assertNotFound();
});

it('creates an item with the attributes of its type, and gives the default types on first use', function (): void {
    CiType::query()->forceDelete();
    Livewire::test(ItemIndex::class);
    $server = CiType::query()->where('key', 'server')->sole();

    Livewire::test(ItemForm::class)
        ->set('typeId', $server->id)
        ->assertSee('Operating system')
        ->set('name', 'srv-app-01')
        ->set('identifier', 'SRV-APP-01')
        ->set('properties.operating_system', 'Debian 13')
        ->set('properties.memory_gb', 'many')
        ->call('save')
        ->assertHasErrors(['properties.memory_gb'])
        ->set('properties.memory_gb', '32')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(ConfigurationItem::query()->where('identifier', 'SRV-APP-01')->sole()->properties)->toEqual(['operating_system' => 'Debian 13', 'memory_gb' => 32]);
});

it('refuses duplicate identifiers in the form', function (): void {
    $this->cmdb->item('a', ['identifier' => 'SRV-01']);

    Livewire::test(ItemForm::class)
        ->set('typeId', $this->cmdb->type->id)->set('name', 'b')->set('identifier', 'srv-01')
        ->call('save')
        ->assertHasErrors(['identifier']);
});

it('edits an item, and lets only staff who manage the CMDB do it', function (): void {
    $item = $this->cmdb->item('mail-01');

    Livewire::test(ItemForm::class, ['item' => $item])
        ->assertSet('name', 'mail-01')
        ->set('status', 'maintenance')
        ->call('save')
        ->assertHasNoErrors();
    expect($item->fresh()->status->value)->toBe('maintenance');

    $cab = $this->cmdb->member(DefaultRole::CabMember);
    $this->actingAs($cab)->get(route('cmdb.items.show', $item))->assertOk();
    $this->actingAs($cab)->get(route('cmdb.items.edit', $item))->assertForbidden();
});

it('adds and removes relationships from the item page, and shows impact and the map', function (): void {
    $app = $this->cmdb->item('payroll-app');
    $vm = $this->cmdb->item('vm-07');

    Livewire::test(ShowItem::class, ['item' => $app])
        ->set('direction', 'outgoing')
        ->set('relationshipType', 'runs_on')
        ->set('otherSearch', 'vm-07')
        ->assertSee('vm-07')
        ->set('otherId', $vm->id)
        ->call('addRelationship')
        ->assertHasNoErrors()
        ->assertSee('runs on')
        ->assertSee('x-data="ciGraph"', false);

    $relationship = Relationship::query()->sole();
    expect($relationship->source_id)->toBe($app->id)->and($relationship->type)->toBe(RelationshipType::RunsOn);

    Livewire::test(ShowItem::class, ['item' => $vm])
        ->assertSee('hosts')
        ->assertSee(__('cmdb::relationships.impact.affected'))
        ->assertSee('payroll-app')
        ->call('removeRelationship', $relationship->id);

    expect(Relationship::query()->count())->toBe(0);
});

it('refuses relationships to items of other organizations', function (): void {
    $mine = $this->cmdb->item('mine');
    $theirs = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => ConfigurationItem::factory()->create());

    Livewire::test(ShowItem::class, ['item' => $mine])
        ->set('otherId', $theirs->id)
        ->call('addRelationship')
        ->assertNotFound();
});

it('lets team leads manage types, with fixed keys, and not agents', function (): void {
    Filament::setCurrentPanel('admin');
    $this->cmdb->lead->givePermissionTo('core.admin.access');
    $this->cmdb->agent->givePermissionTo('core.admin.access');

    $this->actingAs($this->cmdb->agent)->get('/admin/cmdb/types')->assertForbidden();
    $this->actingAs($this->cmdb->lead)->get('/admin/cmdb/types')->assertOk();

    Livewire::test(ManageCiTypes::class)
        ->callAction('create', data: ['name' => 'Printer', 'key' => 'printer', 'is_active' => true, 'attribute_schema' => [['label' => 'IP address', 'key' => 'ip_address', 'type' => 'text', 'required' => false]]])
        ->assertHasNoFormErrors()
        ->callAction('create', data: ['name' => 'Bad', 'key' => 'Bad Key'])
        ->assertHasFormErrors(['key']);

    expect(CiType::query()->where('key', 'printer')->sole()->schema()->fields[0]->key)->toBe('ip_address');
});
