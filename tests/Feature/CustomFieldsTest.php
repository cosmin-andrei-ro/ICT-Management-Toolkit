<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Modules\Applications\Domain\Models\Application;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\CustomFields\CustomFieldSchema;
use Modules\Core\Domain\CustomFields\CustomFieldTypes;
use Modules\Core\Domain\CustomFields\CustomFieldValues;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Resources\CustomFields\Pages\ManageCustomFields;
use Modules\Core\Http\Livewire\CustomFields\RecordCustomFields;
use Modules\Itam\Domain\Models\Asset;
use Modules\Projects\Domain\Models\Project;
use Modules\Risk\Domain\Models\Risk;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    app(OrganizationContext::class)->set(Organization::query()->sole());
    $this->admin = User::query()->where('email', 'admin@example.com')->sole();
    $this->agent = User::query()->where('email', 'agent@example.com')->sole();
    $this->employee = User::query()->where('email', 'employee@example.com')->sole();
    $this->item = ConfigurationItem::query()->where('name', 'ERP')->sole();
    $this->schema = CustomFieldSchema::query()->create(['entity_type' => 'cmdb.configuration_item', 'schema' => [
        ['key' => 'cost_centre', 'label' => 'Cost centre', 'type' => 'text', 'required' => true],
        ['key' => 'tier', 'label' => 'Tier', 'type' => 'select', 'options' => ['Gold', 'Silver']],
        ['key' => 'audited', 'label' => 'Audited', 'type' => 'checkbox'],
    ]]);
});

it('shows nothing when a kind of record has no fields', function (): void {
    Livewire::actingAs($this->admin)->test(RecordCustomFields::class, ['record' => Supplier::query()->firstOrFail()])
        ->assertDontSee(__('core::custom_fields.title'));
});

it('lets someone who may change the record fill the fields in, and records it in its history', function (): void {
    Livewire::actingAs($this->agent)->test(RecordCustomFields::class, ['record' => $this->item])
        ->assertSee('Cost centre')
        ->call('edit')
        ->set('answers.cost_centre', 'CC-42')->set('answers.tier', 'Gold')->set('answers.audited', true)
        ->call('save')->assertHasNoErrors()
        ->assertSee('CC-42')->assertSet('editing', false);

    expect(CustomFieldValues::query()->where('entity_id', $this->item->id)->sole()->field_values)
        ->toMatchArray(['cost_centre' => 'CC-42', 'tier' => 'Gold', 'audited' => true]);

    $entry = AuditLog::query()->where('auditable_id', $this->item->id)->where('event', AuditEvent::Updated->value)->latest('created_at')->first();
    expect($entry?->new_values['custom_fields']['cost_centre'] ?? null)->toBe('CC-42');
});

it('validates against the schema', function (): void {
    $component = Livewire::actingAs($this->agent)->test(RecordCustomFields::class, ['record' => $this->item])->call('edit');
    $component->set('answers.cost_centre', '')->call('save')->assertHasErrors('answers.cost_centre');
    $component->set('answers.cost_centre', 'CC')->set('answers.tier', 'Platinum')->call('save')->assertHasErrors('answers.tier');
    $component->set('answers.tier', 'Silver')->set('answers.unknown', 'injected')->call('save')->assertHasNoErrors();

    expect(CustomFieldValues::query()->where('entity_id', $this->item->id)->sole()->field_values)->not->toHaveKey('unknown');
});

it('works on each kind of record', function (string $class, array $open, string $type, string $ability): void {
    $record = $class::query()->where($open)->firstOrFail();
    CustomFieldSchema::query()->updateOrCreate(['entity_type' => $type], ['schema' => [['key' => 'note', 'label' => 'Note', 'type' => 'text']]]);

    expect(app(CustomFieldTypes::class)->all())->toHaveKey($type);
    Livewire::actingAs($this->admin)->test(RecordCustomFields::class, ['record' => $record, 'ability' => $ability])
        ->call('edit')->set('answers.note', 'Hello')->call('save')->assertHasNoErrors()->assertSee('Hello');
})->with([
    'ticket' => [Ticket::class, ['closed_at' => null], 'ticketing.ticket', 'work'],
    'configuration item' => [ConfigurationItem::class, [], 'cmdb.configuration_item', 'update'],
    'asset' => [Asset::class, [], 'itam.asset', 'update'],
    'application' => [Application::class, [], 'applications.application', 'update'],
    'risk' => [Risk::class, ['status' => 'open'], 'risk.risk', 'update'],
    'project' => [Project::class, ['status' => 'active'], 'projects.project', 'update'],
    'supplier' => [Supplier::class, [], 'core.supplier', 'update'],
]);

it('lets someone who may only view the record read but not change it', function (): void {
    $auditor = User::query()->where('email', 'auditor@example.com')->sole();
    $supplier = Supplier::query()->firstOrFail();
    CustomFieldSchema::query()->create(['entity_type' => 'core.supplier', 'schema' => [['key' => 'note', 'label' => 'Note', 'type' => 'text']]]);

    $component = Livewire::actingAs($auditor)->test(RecordCustomFields::class, ['record' => $supplier])
        ->assertSee('Note')->assertDontSee(__('core::custom_fields.edit'));
    $component->call('edit')->assertForbidden();
});

it('refuses those who may not view the record', function (): void {
    Livewire::actingAs($this->employee)->test(RecordCustomFields::class, ['record' => $this->item])->assertForbidden();
});

it('keeps values of a removed field, in case it comes back', function (): void {
    Livewire::actingAs($this->agent)->test(RecordCustomFields::class, ['record' => $this->item])
        ->call('edit')->set('answers.cost_centre', 'CC-42')->call('save');
    $this->schema->update(['schema' => [['key' => 'tier', 'label' => 'Tier', 'type' => 'text']]]);

    Livewire::actingAs($this->agent)->test(RecordCustomFields::class, ['record' => $this->item])
        ->assertDontSee('CC-42')->call('edit')->set('answers.tier', 'Gold')->call('save');

    expect(CustomFieldValues::query()->where('entity_id', $this->item->id)->sole()->field_values)->toMatchArray(['cost_centre' => 'CC-42', 'tier' => 'Gold']);
});

it('keeps organizations apart', function (): void {
    Livewire::actingAs($this->agent)->test(RecordCustomFields::class, ['record' => $this->item])
        ->call('edit')->set('answers.cost_centre', 'CC-42')->call('save');
    app(OrganizationContext::class)->set(Organization::factory()->create());

    expect(CustomFieldSchema::query()->count())->toBe(0)->and(CustomFieldValues::query()->count())->toBe(0);
});

it('lets only administrators define fields, one schema per kind, valid ones only', function (): void {
    Livewire::actingAs($this->admin)->test(ManageCustomFields::class)
        ->callAction(TestAction::make('create'), ['entity_type' => 'itam.asset', 'schema' => [['key' => 'bay', 'label' => 'Bay', 'type' => 'text', 'required' => false]]])
        ->assertHasNoFormErrors();
    expect(CustomFieldSchema::query()->where('entity_type', 'itam.asset')->sole()->schema[0]['key'])->toBe('bay');

    Livewire::actingAs($this->admin)->test(ManageCustomFields::class)
        ->callAction(TestAction::make('create'), ['entity_type' => 'itam.asset', 'schema' => []])->assertHasFormErrors(['entity_type']);
    Livewire::actingAs($this->admin)->test(ManageCustomFields::class)
        ->callAction(TestAction::make('create'), ['entity_type' => 'not.a.kind', 'schema' => []])->assertHasFormErrors(['entity_type']);

    expect(fn () => CustomFieldSchema::query()->create(['entity_type' => 'risk.risk', 'schema' => [['key' => 'x']]]))->toThrow(InvalidArgumentException::class);

    $this->actingAs($this->agent)->withoutMiddleware(Modules\Core\Http\Middleware\RequireTwoFactor::class)->get('/admin/custom-fields')->assertForbidden();
});
