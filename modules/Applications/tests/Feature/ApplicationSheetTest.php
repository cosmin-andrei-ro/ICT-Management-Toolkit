<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Applications\Application\Notifications\SheetUpdate;
use Modules\Applications\Domain\Enums\SheetStatus;
use Modules\Applications\Domain\Models\Application;
use Modules\Applications\Domain\Models\OptionValue;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Http\Filament\Resources\OptionValues\Pages\ManageOptionValues;
use Modules\Applications\Http\Livewire\ApplicationForm;
use Modules\Applications\Http\Livewire\ApplicationIndex;
use Modules\Applications\Http\Livewire\MyApplications;
use Modules\Applications\Http\Livewire\SheetPage;
use Modules\Applications\Http\Livewire\ShowApplication;
use Modules\Applications\Tests\Support\ApplicationsScenario;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\Supplier;
use Modules\Core\Domain\Tenancy\OrganizationContext;

beforeEach(function (): void {
    Notification::fake();
    $this->apps = ApplicationsScenario::create();
});

/**
 * The technical owner writes chapter 1 of the Ticketing Platform and submits it.
 */
function writeTicketingSheet(ApplicationsScenario $apps): Sheet
{
    test()->actingAs($apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $apps->ticketing])->call('startSheet');
    $sheet = Sheet::query()->where('application_id', $apps->ticketing->id)->sole();

    Livewire::test(SheetPage::class, ['sheet' => $sheet])
        ->assertSet('form.doc.document_owner', $apps->technicalOwner->id)
        ->call('openChapter', 1)
        ->set('form.1_1.description', 'Internal IT support application.')
        ->set('form.1_2_1.internal_support.service_desk', 'servicedesk@company.example')
        ->set('form.1_3.status', 'in_production')
        ->set('form.1_3.criticality', 'high')
        ->set('form.1_3.exposure', ['internal'])
        ->set('form.1_3.cartography', '__other')
        ->set('form.1_3.cartography_other', 'Shared IT service')
        ->call('addRow', '1_4', 'environments')
        ->set('form.1_4.environments.0.environment', 'production')
        ->set('form.1_4.environments.0.url', 'https://servicedesk.company.local')
        ->call('openChapter', 2)
        ->set('form.2_1.classification', 'internal')
        ->call('submit')
        ->assertHasErrors('changeSummary')
        ->set('changeSummary', 'Initial documentation of the existing Ticketing Platform')
        ->call('submit')
        ->assertHasNoErrors();

    return $sheet->fresh();
}

it('lets application managers keep the register, owners their applications, and IT staff read', function (): void {
    $this->actingAs($this->apps->manager);
    Livewire::test(ApplicationForm::class)
        ->set('name', 'Payroll')->set('code', 'app-pay')
        ->set('businessOwnerId', $this->apps->businessOwner->id)->set('technicalOwnerId', $this->apps->technicalOwner->id)
        ->call('save')->assertHasNoErrors();
    expect(Application::query()->where('name', 'Payroll')->sole()->code)->toBe('APP-PAY');
    Livewire::test(ApplicationForm::class)->set('name', 'payroll')->set('code', 'APP-PAY')->call('save')->assertHasErrors(['name', 'code']);

    $this->actingAs($this->apps->agent);
    Livewire::test(ApplicationIndex::class)->assertSee('Ticketing Platform')->assertDontSee('New application');
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->assertDontSee('Write the documentation');
    Livewire::test(ApplicationForm::class)->assertForbidden();

    // A business owner outside IT finds their applications and documents them.
    $this->actingAs($this->apps->businessOwner);
    Livewire::test(MyApplications::class)->assertSee('Ticketing Platform')->assertSee('Payroll');
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->assertSee('Write the documentation');
    Livewire::test(ApplicationIndex::class)->assertForbidden();

    $this->actingAs($this->apps->employee);
    $this->get(route('applications.show', $this->apps->ticketing))->assertForbidden();

    app(OrganizationContext::class)->set(Organization::factory()->create());
    $other = Application::factory()->create();
    app(OrganizationContext::class)->set($this->apps->organization);
    $this->actingAs($this->apps->manager)->get(route('applications.show', $other->id))->assertNotFound();
});

it('approves the first version by the business owner, the IT owner and security, never by the submitter', function (): void {
    $sheet = writeTicketingSheet($this->apps);
    expect($sheet->status)->toBe(SheetStatus::Submitted)
        ->and($sheet->section('1_3')['cartography'])->toBe('Shared IT service');
    Notification::assertSentTo([$this->apps->businessOwner, $this->apps->security], SheetUpdate::class);
    Notification::assertNotSentTo($this->apps->technicalOwner, SheetUpdate::class);

    // The technical owner submitted it: a manager stands in for the IT approval.
    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->set('approvalRole', 'technical')->call('approve')->assertForbidden();

    $this->actingAs($this->apps->businessOwner);
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->set('approvalRole', 'business')->call('approve')->assertHasNoErrors();

    $this->actingAs($this->apps->manager);
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->assertDontSee('Security officer</label>', false)
        ->set('approvalRole', 'business')->call('approve')->assertHasErrors('approvalRole')
        ->set('approvalRole', 'technical')->call('approve')->assertHasNoErrors()
        ->set('approvalRole', 'technical')->call('approve')->assertForbidden();
    expect($sheet->fresh()->status)->toBe(SheetStatus::Submitted);

    $this->actingAs($this->apps->security);
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->set('approvalRole', 'security')->call('approve')->assertHasNoErrors();

    $sheet->refresh();
    $application = $this->apps->ticketing->fresh();
    expect($sheet->status)->toBe(SheetStatus::Approved)->and($sheet->versionLabel())->toBe('1.0')
        ->and($application->current_version)->toBe('1.0')
        ->and($application->status)->toBe('in_production')
        ->and($application->criticality)->toBe('high')
        ->and($application->next_review_on?->toDateString())->toBe(now()->addYear()->toDateString());
    Notification::assertSentTo($this->apps->technicalOwner, SheetUpdate::class, fn (SheetUpdate $update): bool => $update->kind === 'in_force');

    // Other modules (the Projects checklist) learn that it is documented.
    $summary = app(Modules\Core\Domain\Links\Applications::class)->find([$application->id])[$application->id];
    expect($summary->documented)->toBeTrue()
        ->and($summary->configurationItemId)->toBe($application->configuration_item_id)
        ->and($summary->processIds)->toBe(array_values(array_filter((array) ($sheet->section('10_1')['processes'] ?? []), is_string(...))));

    $this->actingAs($this->apps->agent);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])
        ->assertSee('Initial documentation of the existing Ticketing Platform')->assertSee('Security officer');
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->call('openChapter', 1)
        ->assertSee('In production')->assertSee('Shared IT service')->assertSee('https://servicedesk.company.local')->assertSee('servicedesk@company.example');
});

it('returns a version with a reason and clears the approvals given', function (): void {
    $sheet = writeTicketingSheet($this->apps);
    $this->actingAs($this->apps->businessOwner);
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->set('approvalRole', 'business')->call('approve');

    $this->actingAs($this->apps->security);
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->call('sendBack')->assertHasErrors('comment')
        ->set('comment', 'Describe the access of the UAT environment')->call('sendBack')->assertHasNoErrors();

    $sheet->refresh();
    expect($sheet->status)->toBe(SheetStatus::Returned)->and($sheet->approvals()->count())->toBe(0);
    Notification::assertSentTo($this->apps->technicalOwner, SheetUpdate::class, fn (SheetUpdate $update): bool => $update->kind === 'returned');

    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->assertSee('Describe the access of the UAT environment')->call('submit')->assertHasNoErrors();
    expect($sheet->fresh()->status)->toBe(SheetStatus::Submitted);
});

it('publishes minor versions at once and sends major ones for approval', function (): void {
    $first = writeTicketingSheet($this->apps);
    foreach ([[$this->apps->businessOwner, 'business'], [$this->apps->manager, 'technical'], [$this->apps->security, 'security']] as [$user, $role]) {
        $this->actingAs($user);
        Livewire::test(SheetPage::class, ['sheet' => $first])->set('approvalRole', $role)->call('approve');
    }

    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $minor = Sheet::query()->where('application_id', $this->apps->ticketing->id)->where('status', 'draft')->sole();
    expect($minor->is_major)->toBeFalse()->and($minor->section('1_1')['description'])->toBe('Internal IT support application.');

    Livewire::test(SheetPage::class, ['sheet' => $minor])
        ->call('openChapter', 1)->set('form.1_6.maintenance_window', 'Sunday 02:00-06:00')
        ->set('changeSummary', 'Added the maintenance window')->call('submit')->assertHasNoErrors();
    expect($minor->fresh()->versionLabel())->toBe('1.1')->and($first->fresh()->status)->toBe(SheetStatus::Superseded)
        ->and($this->apps->ticketing->fresh()->current_version)->toBe('1.1');

    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $major = Sheet::query()->where('application_id', $this->apps->ticketing->id)->where('status', 'draft')->sole();
    Livewire::test(SheetPage::class, ['sheet' => $major])->set('isMajor', true)->set('changeSummary', 'Moved to the cloud')->call('submit')->assertHasNoErrors();
    expect($major->fresh()->status)->toBe(SheetStatus::Submitted);
});

it('checks links, references and completeness, and offers the organization\'s dropdown values', function (): void {
    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $sheet = Sheet::query()->sole();

    app(OrganizationContext::class)->set(Organization::factory()->create());
    $foreign = Supplier::factory()->create();
    app(OrganizationContext::class)->set($this->apps->organization);

    $page = Livewire::test(SheetPage::class, ['sheet' => $sheet])->call('openChapter', 1)
        ->set('form.1_2_2.support_portal', 'javascript:alert(1)')->call('save')->assertHasErrors('form.1_2_2.support_portal')
        ->set('form.1_2_2.support_portal', 'https://support.example.com')
        ->set('form.1_2_2.supplier', $foreign->id)->call('save')->assertHasErrors('form.1_2_2.supplier')
        ->set('form.1_2_2.supplier', '')->call('save')->assertHasNoErrors()
        ->set('changeSummary', 'Initial documentation')->call('submit')->assertHasErrors('sections');
    expect(collect($page->errors()->get('sections'))->implode(' '))->toContain('1.1 Application Description')->toContain('1.4 Environments')->toContain('2.1 Data Categories');

    // Administrators rename or retire values; sheets keep the key.
    OptionValue::query()->where('list', 'business_criticality')->where('key', 'low')->update(['is_active' => false]);
    OptionValue::query()->where('list', 'business_criticality')->where('key', 'high')->update(['label' => 'Important']);
    app(Modules\Applications\Application\Options\OptionCatalog::class)->forget();
    Livewire::test(SheetPage::class, ['sheet' => $sheet])->call('openChapter', 1)->assertSee('Important')
        ->set('form.1_3.criticality', 'low')->call('save')->assertHasErrors('form.1_3.criticality');
});

it('lets application managers edit the dropdown values, never delete them', function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->apps->manager);
    $this->get('/admin/applications/dropdown-values')->assertOk();
    expect(OptionValue::query()->where('list', 'support_teams')->count())->toBe(15);

    Livewire::test(ManageOptionValues::class)
        ->callAction('create', data: ['list' => 'environments', 'label' => 'Sandbox', 'key' => 'sandbox', 'position' => 10, 'is_active' => true])
        ->assertHasNoFormErrors()
        ->callAction('create', data: ['list' => 'environments', 'label' => 'Sandbox 2', 'key' => 'sandbox', 'position' => 10, 'is_active' => true])
        ->assertHasFormErrors(['key']);
    expect(OptionValue::query()->where('key', 'sandbox')->sole()->label)->toBe('Sandbox');

    $this->actingAs($this->apps->agent)->get('/admin/applications/dropdown-values')->assertForbidden();
});
