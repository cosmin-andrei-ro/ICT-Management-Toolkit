<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Applications\Application\Sheets\SheetForm;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Http\Livewire\SheetPage;
use Modules\Applications\Http\Livewire\ShowApplication;
use Modules\Applications\Tests\Support\ApplicationsScenario;
use Modules\Core\Domain\Models\Attachment;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('attachments');
    $this->apps = ApplicationsScenario::create();
    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $this->sheet = Sheet::query()->sole();
});

it('records the data categories and elements processed, and nothing outside the template', function (): void {
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 2)
        ->set('form.2_1.classification', 'confidential')
        ->set('form.2_1.categories.personal', ['name', 'contact', 'shoe_size'])
        ->set('form.2_1.categories.communications', ['support_tickets'])
        ->set('form.2_1.categories_other', 'Satisfaction survey answers')
        ->call('save')->assertHasNoErrors();

    // jsonb keeps the keys in its own order.
    expect($this->sheet->fresh()->section('2_1'))->toEqual([
        'classification' => 'confidential',
        'categories' => ['personal' => ['name', 'contact'], 'communications' => ['support_tickets']],
        'categories_other' => 'Satisfaction survey answers',
    ]);

    $this->actingAs($this->apps->agent);
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 2)
        ->assertSee('Confidential')->assertSee('Personal Data (GDPR)')->assertSee('Name, Contact details')->assertSee('Support tickets')
        ->assertDontSee('Payment Card Data (PCI DSS)')->assertSee('Satisfaction survey answers');
});

it('draws the data flow diagram from the integrations', function (): void {
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 2)
        ->assertSee('Add the system integrations in 2.4')
        ->call('addRow', '2_4', 'integrations')->call('addRow', '2_4', 'integrations')
        ->set('form.2_4.integrations.0.name', 'Import users')->set('form.2_4.integrations.0.system', 'Active Directory')->set('form.2_4.integrations.0.direction', 'inbound')
        ->set('form.2_4.integrations.1.name', 'Notifications')->set('form.2_4.integrations.1.system', 'Mail Service')->set('form.2_4.integrations.1.direction', 'bidirectional')
        ->set('form.2_4.integrations.1.type', SheetForm::OTHER)->set('form.2_4.integrations.1.type_other', 'Graph API')
        ->call('save')->assertHasNoErrors()
        ->assertSeeHtml('<title id="dfd-title">Data flow diagram of Ticketing Platform</title>')
        ->assertSee('Active Directory')->assertSee('Mail Service');

    expect($this->sheet->fresh()->section('2_4')['integrations'][1]['type'])->toBe('Graph API');
});

it('attaches an image and a .drawio file to a diagram, shows only images inline, and keeps them for the application', function (): void {
    $page = Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 2)
        ->set('diagramImage', UploadedFile::fake()->createWithContent('flow.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
        ->call('attachDiagram', '2_2', 'diagram')->assertHasErrors('diagramImage')
        ->set('diagramImage', UploadedFile::fake()->image('flow.png', 40, 30))
        ->set('diagramSource', UploadedFile::fake()->createWithContent('flow.drawio', '<?xml version="1.0" encoding="UTF-8"?><mxfile host="app.diagrams.net"><diagram id="a">x</diagram></mxfile>'))
        ->call('attachDiagram', '2_2', 'diagram')->assertHasNoErrors();

    $diagram = $this->sheet->fresh()->section('2_2')['diagram'];
    $image = Attachment::query()->findOrFail($diagram['image']);
    $source = Attachment::query()->findOrFail($diagram['source']);
    expect($image->attachable_id)->toBe($this->apps->ticketing->id)->and($image->mime_type)->toBe('image/png')
        ->and($source->original_name)->toBe('flow.drawio');
    $page->assertSee('Download the source file (flow.drawio)');

    $this->actingAs($this->apps->agent);
    $this->get(route('applications.diagrams.image', $image))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('applications.diagrams.image', $source))->assertNotFound();
    $this->get(route('core.attachments.download', $source))->assertOk();

    $this->actingAs($this->apps->employee);
    $this->get(route('applications.diagrams.image', $image))->assertForbidden();
});

it('fills the role permissions matrix from the functionalities and the roles', function (): void {
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 2)
        ->call('addRow', '2_3', 'functionalities')->call('addRow', '2_3', 'functionalities')
        ->set('form.2_3.functionalities.0.module', 'Tickets')->set('form.2_3.functionalities.0.menu', 'New Ticket')->set('form.2_3.functionalities.0.functionality', 'Create ticket')
        ->set('form.2_3.functionalities.1.module', 'Tickets')->set('form.2_3.functionalities.1.menu', 'Ticket Details')->set('form.2_3.functionalities.1.functionality', 'Assign ticket')
        ->call('openChapter', 3)
        ->assertSee('List the functionalities in 2.3 and the roles in 3.3')
        ->call('addRow', '3_3', 'roles')->call('addRow', '3_3', 'roles')
        ->set('form.3_3.roles.0.name', 'Requester')->set('form.3_3.roles.1.name', 'Team Lead')
        ->call('save')->assertHasNoErrors();

    $create = SheetForm::hash('Tickets|New Ticket|Create ticket');
    $assign = SheetForm::hash('Tickets|Ticket Details|Assign ticket');
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 3)
        ->set("form.3_4.matrix.{$create}.".SheetForm::hash('Requester'), true)
        ->set("form.3_4.matrix.{$create}.".SheetForm::hash('Team Lead'), true)
        ->set("form.3_4.matrix.{$assign}.".SheetForm::hash('Team Lead'), true)
        ->call('save')->assertHasNoErrors();

    expect($this->sheet->fresh()->section('3_4')['matrix'])->toEqual([
        ['module' => 'Tickets', 'menu' => 'New Ticket', 'functionality' => 'Create ticket', 'roles' => ['Requester', 'Team Lead']],
        ['module' => 'Tickets', 'menu' => 'Ticket Details', 'functionality' => 'Assign ticket', 'roles' => ['Team Lead']],
    ]);

    $this->actingAs($this->apps->agent);
    Livewire::test(SheetPage::class, ['sheet' => $this->sheet])->call('openChapter', 3)
        ->assertSee('Assign ticket')->assertSee('Team Lead')->assertSeeHtml('aria-label="Allowed">✓</span>');
});
