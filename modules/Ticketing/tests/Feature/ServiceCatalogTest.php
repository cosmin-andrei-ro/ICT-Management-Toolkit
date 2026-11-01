<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Ticketing\Domain\Enums\Impact;
use Modules\Ticketing\Domain\Enums\Priority;
use Modules\Ticketing\Domain\Enums\TicketType;
use Modules\Ticketing\Domain\Enums\Urgency;
use Modules\Ticketing\Domain\Models\Queue;
use Modules\Ticketing\Domain\Models\Service;
use Modules\Ticketing\Domain\Models\Ticket;
use Modules\Ticketing\Http\Filament\Resources\Services\Pages\ManageServices;
use Modules\Ticketing\Http\Livewire\Catalog\RequestServiceForm;
use Modules\Ticketing\Http\Livewire\Catalog\ServiceCatalog;
use Modules\Ticketing\Tests\Support\TicketingScenario;

beforeEach(function (): void {
    Notification::fake();
    $this->desk = TicketingScenario::create();
    $this->hardwareQueue = Queue::factory()->create(['team_id' => $this->desk->team->id, 'name' => 'Hardware']);

    $this->laptop = Service::factory()->create([
        'name' => 'New laptop',
        'description' => 'For new joiners and replacements.',
        'queue_id' => $this->hardwareQueue->id,
        'impact' => Impact::Low,
        'urgency' => Urgency::Medium,
        'form_schema' => [
            ['key' => 'model', 'label' => 'Model', 'type' => 'select', 'required' => true, 'options' => ['Standard', 'Developer']],
            ['key' => 'needed_by', 'label' => 'Needed by', 'type' => 'date', 'required' => true],
        ],
    ]);
});

it('lists active services of the organization', function (): void {
    Service::factory()->create(['name' => 'Retired service', 'is_active' => false]);
    $foreign = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Service::factory()->create(['name' => 'Other org service']));

    $this->actingAs($this->desk->requester);

    Livewire::test(ServiceCatalog::class)
        ->assertSee('New laptop')
        ->assertDontSee('Retired service')
        ->assertDontSee($foreign->name)
        ->set('search', 'joiners')
        ->assertSee('New laptop');
});

it('raises a service request routed and prioritized by the service', function (): void {
    $this->actingAs($this->desk->requester);

    Livewire::test(RequestServiceForm::class, ['service' => $this->laptop])
        ->set('answers.model', 'Developer')
        ->set('answers.needed_by', '2026-11-20')
        ->set('details', 'Starts on the 23rd.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $ticket = Ticket::query()->sole();
    expect($ticket->type)->toBe(TicketType::ServiceRequest)
        ->and($ticket->number)->toBe('REQ-000001')
        ->and($ticket->title)->toBe('New laptop')
        ->and($ticket->service_id)->toBe($this->laptop->id)
        ->and($ticket->queue_id)->toBe($this->hardwareQueue->id)
        ->and($ticket->priority)->toBe(Priority::Low)
        // toEqual: jsonb stores object keys in its own order.
        ->and($ticket->serviceAnswers())->toEqual([
            ['key' => 'model', 'label' => 'Model', 'type' => 'select', 'value' => 'Developer'],
            ['key' => 'needed_by', 'label' => 'Needed by', 'type' => 'date', 'value' => '2026-11-20'],
        ]);
});

it('shows form errors next to the right field', function (): void {
    $this->actingAs($this->desk->requester);

    Livewire::test(RequestServiceForm::class, ['service' => $this->laptop])
        ->set('answers.model', 'Gaming rig')
        ->call('submit')
        ->assertHasErrors(['answers.model', 'answers.needed_by']);

    expect(Ticket::query()->count())->toBe(0);
});

it('keeps past answers readable after the form changes', function (): void {
    $this->actingAs($this->desk->requester);
    Livewire::test(RequestServiceForm::class, ['service' => $this->laptop])
        ->set('answers.model', 'Standard')->set('answers.needed_by', '2026-11-20')->call('submit');

    $this->laptop->update(['form_schema' => [['key' => 'cpu', 'label' => 'CPU', 'type' => 'text']]]);

    $this->actingAs($this->desk->agent)
        ->get(route('ticketing.desk.show', Ticket::query()->sole()))
        ->assertOk()
        ->assertSee('Needed by')
        ->assertSee('2026-11-20')
        ->assertDontSee('CPU');
});

it('refuses inactive services and services of other organizations', function (): void {
    $this->laptop->update(['is_active' => false]);
    $foreign = app(OrganizationContext::class)->runAs(Organization::factory()->create(), fn () => Service::factory()->create());

    $this->actingAs($this->desk->requester)->get(route('ticketing.catalog.request', $this->laptop))->assertNotFound();
    $this->actingAs($this->desk->requester)->get(route('ticketing.catalog.request', $foreign))->assertNotFound();
});

it('falls back to the default queue when the service queue was deactivated', function (): void {
    $this->hardwareQueue->update(['is_active' => false]);
    $this->actingAs($this->desk->requester);

    Livewire::test(RequestServiceForm::class, ['service' => $this->laptop])
        ->set('answers.model', 'Standard')->set('answers.needed_by', '2026-11-20')->call('submit')
        ->assertHasNoErrors();

    expect(Ticket::query()->sole()->queue_id)->toBe($this->desk->queue->id);
});

it('never stores an invalid form definition', function (): void {
    $this->laptop->update(['form_schema' => [['key' => 'Bad Key', 'label' => 'x', 'type' => 'text']]]);
})->throws(InvalidArgumentException::class);

describe('administration', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel('admin');
        app(OrganizationContext::class)->runAs($this->desk->organization, fn () => $this->desk->lead->givePermissionTo('core.admin.access'));
        $this->actingAs($this->desk->lead);
    });

    it('builds a service with its request form', function (): void {
        Livewire::test(ManageServices::class)
            ->callAction('create', data: [
                'name' => 'VPN access',
                'impact' => 'low',
                'urgency' => 'low',
                'is_active' => true,
                'form_schema' => [
                    ['label' => 'Reason', 'key' => 'reason', 'type' => 'textarea', 'required' => true],
                    ['label' => 'Region', 'key' => 'region', 'type' => 'select', 'options' => ['EU', 'US']],
                ],
            ])
            ->assertHasNoFormErrors();

        $service = Service::query()->where('name', 'VPN access')->sole();
        expect($service->form()->fields)->toHaveCount(2)
            ->and($service->form_schema[1]['options'])->toBe(['EU', 'US']);
    });

    it('rejects duplicate field keys as a form error', function (): void {
        Livewire::test(ManageServices::class)
            ->callAction('create', data: [
                'name' => 'Broken',
                'impact' => 'low',
                'urgency' => 'low',
                'form_schema' => [
                    ['label' => 'A', 'key' => 'same', 'type' => 'text'],
                    ['label' => 'B', 'key' => 'same', 'type' => 'text'],
                ],
            ])
            ->assertHasFormErrors(['form_schema']);

        expect(Service::query()->where('name', 'Broken')->exists())->toBeFalse();
    });
});
