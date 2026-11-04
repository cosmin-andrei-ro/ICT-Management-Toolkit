<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Modules\Core\Application\Authorization\SyncPermissions;
use Modules\Core\Application\Exports\PersonalDataExport;
use Modules\Core\Domain\Audit\AuditEvent;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Authorization\DefaultRole;
use Modules\Core\Domain\Exports\ExportSheet;
use Modules\Core\Domain\Models\Comment;
use Modules\Core\Domain\Models\Export;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Core\Http\Filament\Pages\PersonalDataPage;
use Modules\Risk\Domain\Models\Risk;
use Modules\Ticketing\Domain\Models\Ticket;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    app(SyncPermissions::class)->handle();
    $this->organization = Organization::factory()->create();
    app(OrganizationContext::class)->set($this->organization);
    $this->admin = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $this->admin->assignRole(DefaultRole::Administrator->value);
    $this->person = User::factory()->memberOf($this->organization)->create(['name' => 'Ana Person', 'email' => 'ana@example.com']);
    $this->person->assignRole(DefaultRole::Requester->value);
});

/**
 * @return array<string, list<list<mixed>>> Rows by sheet name.
 */
function personalDataOf(Export $export): array
{
    $sheets = [];

    foreach (app(PersonalDataExport::class)->sheets($export) as $sheet) {
        /** @var ExportSheet $sheet */
        $sheets[$sheet->name] = array_map(fn (array $row): array => $row, iterator_to_array((fn () => yield from $sheet->rows)(), false));
    }

    return $sheets;
}

function personalDataExport(Organization $organization, User $admin, User $person): Export
{
    $export = new Export;
    $export->forceFill([
        'organization_id' => $organization->id, 'user_id' => $admin->id, 'type' => PersonalDataExport::TYPE, 'format' => 'xlsx',
        'status' => 'pending', 'parameters' => ['person_id' => $person->id],
    ])->save();

    return $export;
}

it('is for administrators; others cannot open the page', function (): void {
    $lead = User::factory()->memberOf($this->organization)->withTwoFactor()->create();
    $lead->assignRole(DefaultRole::TeamLead->value);
    $lead->givePermissionTo('core.admin.access');

    $this->actingAs($this->admin)->get('/admin/personal-data')->assertOk()->assertSee('Personal data');
    $this->actingAs($lead)->get('/admin/personal-data')->assertForbidden();
});

it('prepares the export of a member, and records it in the audit log', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(PersonalDataPage::class)->set('data.person_id', $this->person->id)->call('prepare')->assertHasNoErrors();

    $export = Export::query()->where('type', PersonalDataExport::TYPE)->sole();
    expect($export->parameters['person_id'])->toBe($this->person->id)
        ->and(AuditLog::query()->where('event', AuditEvent::Exported)->where('auditable_id', $export->id)->exists())->toBeTrue();
    Livewire::test(PersonalDataPage::class)->assertSee('For Ana Person');
});

it('refuses someone who is not a member of the organization', function (): void {
    $stranger = User::factory()->memberOf(Organization::factory()->create())->create();
    $this->actingAs($this->admin);

    Livewire::test(PersonalDataPage::class)->set('data.person_id', $stranger->id)->call('prepare')->assertHasErrors(['data.person_id']);
    expect(Export::query()->count())->toBe(0);

    // Even an export made up by hand only lists an empty account sheet.
    expect(personalDataOf(personalDataExport($this->organization, $this->admin, $stranger)))->toBe(['Account' => []]);
});

it('lists the account, the records each module names the person in, what they wrote, and their activity', function (): void {
    $ticket = Ticket::factory()->create(['requester_id' => $this->person->id, 'title' => 'Laptop screen flickers', 'description' => 'Since Monday, after the update.']);
    Ticket::factory()->create(['title' => 'Someone else entirely']);
    $risk = Risk::factory()->create(['owner_id' => $this->person->id, 'title' => 'Ana owns this risk']);
    (new Comment)->forceFill(['commentable_type' => $ticket->getMorphClass(), 'commentable_id' => $ticket->id, 'author_id' => $this->person->id, 'body' => 'Still flickering today.', 'is_internal' => false])->save();
    $this->actingAs($this->person);
    $ticket->forceFill(['title' => 'Laptop screen flickers badly'])->save();

    $sheets = personalDataOf(personalDataExport($this->organization, $this->admin, $this->person));
    $flat = json_encode($sheets);

    expect(array_keys($sheets))->toContain('Account', 'Ticketing', 'Risks', 'Comments', 'Files', 'Activity')
        ->and($sheets['Account'])->toContain(['Email', 'ana@example.com'])
        ->and($flat)->toContain('Laptop screen flickers badly')->toContain('Since Monday, after the update.')->toContain('Requester')
        ->toContain('Ana owns this risk')->toContain($risk->code())
        ->toContain('Still flickering today.')
        ->not->toContain('Someone else entirely');
    expect(collect($sheets['Activity'])->pluck(1)->all())->toContain('Changed');
});

it('keeps to the organization the export is made in', function (): void {
    $other = Organization::factory()->create();
    $this->person->organizations()->attach($other);
    app(OrganizationContext::class)->runAs($other, fn () => Ticket::factory()->create(['requester_id' => $this->person->id, 'title' => 'Ticket in the other company']));
    Ticket::factory()->create(['requester_id' => $this->person->id, 'title' => 'Ticket here']);

    $export = personalDataExport($this->organization, $this->admin, $this->person);
    $flat = app(OrganizationContext::class)->runAs($this->organization, fn () => json_encode(personalDataOf($export)));

    expect($flat)->toContain('Ticket here')->not->toContain('Ticket in the other company');
});
