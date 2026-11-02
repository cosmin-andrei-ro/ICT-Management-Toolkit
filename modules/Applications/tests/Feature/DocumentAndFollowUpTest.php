<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Applications\Application\Exports\PortfolioExport;
use Modules\Applications\Application\Notifications\PortfolioDigest;
use Modules\Applications\Application\Notifications\ReviewDue;
use Modules\Applications\Application\Reminders\SendPortfolioDigest;
use Modules\Applications\Application\Reminders\SendReviewReminders;
use Modules\Applications\Domain\Models\Sheet;
use Modules\Applications\Http\Livewire\SheetPage;
use Modules\Applications\Http\Livewire\ShowApplication;
use Modules\Applications\Tests\Support\ApplicationsScenario;
use Modules\Cmdb\Domain\Enums\RelationshipType;
use Modules\Cmdb\Domain\Models\CiType;
use Modules\Cmdb\Domain\Models\ConfigurationItem;
use Modules\Cmdb\Domain\Models\Relationship;
use Modules\Cmdb\Http\Livewire\ShowItem;
use Modules\Core\Application\Exports\RequestExport;
use Modules\Core\Domain\Audit\AuditLog;
use Modules\Core\Domain\Exports\ExportFormat;
use Modules\Core\Domain\Models\Export;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('attachments');
    $this->apps = ApplicationsScenario::create();
});

/**
 * The technical owner documents the Ticketing Platform (with a server linked in
 * the CMDB and a diagram image) and the three approvers bring it into force.
 */
function ticketingInForce(ApplicationsScenario $apps, ?ConfigurationItem $server = null): Sheet
{
    test()->actingAs($apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $apps->ticketing])->call('startSheet');
    $sheet = Sheet::query()->where('application_id', $apps->ticketing->id)->whereIn('status', ['draft'])->sole();
    $page = Livewire::test(SheetPage::class, ['sheet' => $sheet])->call('openChapter', 1)
        ->set('form.1_1.description', 'Internal IT support application.')
        ->set('form.1_3.status', 'in_production')->set('form.1_3.criticality', 'high')
        ->call('addRow', '1_4', 'environments')->set('form.1_4.environments.0.environment', 'production')
        ->call('openChapter', 2)->set('form.2_1.classification', 'internal')->set('form.2_1.categories.communications', ['support_tickets'])
        ->call('addRow', '2_4', 'integrations')->set('form.2_4.integrations.0.name', 'Import users')->set('form.2_4.integrations.0.system', 'Active Directory')->set('form.2_4.integrations.0.direction', 'inbound')
        ->set('diagramImage', UploadedFile::fake()->image('flow.png', 20, 20))->call('attachDiagram', '2_2', 'diagram');

    if ($server !== null) {
        $page->call('openChapter', 5)->call('linkItem', $server->id);
    }

    $page->set('changeSummary', 'Initial documentation')->call('submit')->assertHasNoErrors();

    foreach ([[$apps->businessOwner, 'business'], [$apps->manager, 'technical'], [$apps->security, 'security']] as [$user, $role]) {
        test()->actingAs($user);
        Livewire::test(SheetPage::class, ['sheet' => $sheet])->set('approvalRole', $role)->call('approve')->assertHasNoErrors();
    }

    return $sheet->fresh();
}

it('gives the application its documentation page when its documentation comes into force', function (): void {
    ticketingInForce($this->apps);

    $page = Modules\Documentation\Domain\Models\Page::query()->sole();
    expect($page->title)->toBe('APP-TKT · Ticketing Platform')->and($page->published_body)->toContain('Internal IT support application.');
    // The application's CMDB item lists its page too.
    $itemId = $this->apps->ticketing->fresh()?->configuration_item_id;
    expect($itemId)->not->toBeNull()
        ->and(app(Modules\Core\Domain\Links\ConfigurationItems::class)->recordIdsLinkedTo([(string) $itemId], 'documentation.page'))->toBe([$page->id]);

    $this->actingAs($this->apps->agent);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->assertSee('Documentation pages')->assertSee('APP-TKT · Ticketing Platform');
});

it('lays out the full documentation to print, for those who may read it', function (): void {
    $sheet = ticketingInForce($this->apps);

    $this->actingAs($this->apps->agent);
    $this->get(route('applications.sheets.document', $sheet))->assertOk()
        ->assertSee('Application Technical Documentation')->assertSee('Initial documentation')
        ->assertSee('16. Glossary &amp; Abbreviations', false)->assertSee('Active Directory')->assertSee('Support tickets')
        ->assertDontSee('Download PDF');

    $this->actingAs($this->apps->employee);
    $this->get(route('applications.sheets.document', $sheet))->assertForbidden();
});

it('renders the PDF through the PDF service with everything embedded, and records the export', function (): void {
    $sheet = ticketingInForce($this->apps);
    config(['core.pdf.gotenberg_url' => 'http://gotenberg:3000']);
    // The service answers once, then fails.
    Http::fake(['gotenberg:3000/*' => Http::sequence()->push('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])->push('boom', 500)]);

    $this->actingAs($this->apps->agent);
    $this->get(route('applications.sheets.document', $sheet))->assertSee('Download PDF');
    $this->get(route('applications.sheets.pdf', $sheet))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename=app-tkt-10.pdf');

    Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://gotenberg:3000/forms/chromium/convert/html'
        && str_contains($request->body(), 'data:image/png;base64,')
        && ! str_contains($request->body(), '/applications/diagrams/'));
    expect(AuditLog::query()->where('event', 'exported')->where('auditable_id', $sheet->id)->exists())->toBeTrue();

    // The service down: back to the page, to print from the browser.
    $this->get(route('applications.sheets.pdf', $sheet))->assertRedirect(route('applications.sheets.document', $sheet));
});

it('creates the application in the CMDB when its documentation comes into force, running on its components', function (): void {
    $server = ConfigurationItem::factory()->create(['type_id' => CiType::factory()->create(['key' => 'server'])->id, 'name' => 'SDAPP01']);
    CiType::factory()->create(['key' => 'application', 'name' => 'Application']);
    ticketingInForce($this->apps, $server);

    $application = $this->apps->ticketing->fresh();
    $item = ConfigurationItem::query()->findOrFail($application->configuration_item_id);
    expect($item->name)->toBe('Ticketing Platform')->and($item->identifier)->toBe('APP-TKT')->and($item->criticality->value)->toBe('high')
        ->and(Relationship::query()->where('source_id', $item->id)->where('target_id', $server->id)->where('type', RelationshipType::RunsOn)->exists())->toBeTrue();

    $this->actingAs($this->apps->agent);
    Livewire::test(ShowItem::class, ['item' => $item])->assertSee('Application: Ticketing Platform (APP-TKT)');

    // A later version keeps the same item.
    $this->actingAs($this->apps->technicalOwner);
    Livewire::test(ShowApplication::class, ['application' => $this->apps->ticketing])->call('startSheet');
    $minor = Sheet::query()->where('status', 'draft')->sole();
    Livewire::test(SheetPage::class, ['sheet' => $minor])->set('changeSummary', 'Small fix')->call('submit')->assertHasNoErrors();
    expect(ConfigurationItem::query()->where('identifier', 'APP-TKT')->count())->toBe(1);
});

it('reminds owners of the yearly review once, and tells managers what needs attention', function (): void {
    ticketingInForce($this->apps);
    $this->apps->ticketing->forceFill(['next_review_on' => today()->addDays(10)])->save();

    expect(app(SendReviewReminders::class)->handle())->toBe(1);
    Notification::assertSentTo([$this->apps->businessOwner, $this->apps->technicalOwner], ReviewDue::class);
    expect(app(SendReviewReminders::class)->handle())->toBe(0);

    $this->apps->ticketing->forceFill(['next_review_on' => today()->subDay()])->save();
    expect(app(SendPortfolioDigest::class)->handle())->toBe(1);
    Notification::assertSentTo($this->apps->manager, PortfolioDigest::class, fn (PortfolioDigest $digest): bool => $digest->figures === ['review_overdue' => 1]);
    Notification::assertNotSentTo($this->apps->agent, PortfolioDigest::class);
});

it('exports the portfolio with the data, integrations and certificates in force', function (): void {
    ticketingInForce($this->apps);
    $export = Export::factory()->create(['type' => PortfolioExport::TYPE, 'user_id' => $this->apps->manager->id]);

    [$applications, $data, $integrations] = app(PortfolioExport::class)->sheets($export);
    $row = array_combine($applications->headings, iterator_to_array($applications->rows, false)[0]);
    expect($row)->toMatchArray(['Application code' => 'APP-TKT', 'Status' => 'In production', 'Business criticality' => 'High', 'Version' => '1.0', 'Data classification' => 'Internal'])
        ->and(iterator_to_array($data->rows, false))->toBe([['APP-TKT', 'Communications & Support Data', 'Support tickets']])
        ->and(iterator_to_array($integrations->rows, false)[0])->toBe(['APP-TKT', 'Import users', 'Active Directory', 'Inbound (into the application)', null, null, null]);

    expect(fn () => app(RequestExport::class)->handle($this->apps->employee, PortfolioExport::TYPE, ExportFormat::Xlsx, []))->toThrow(AuthorizationException::class);
});
